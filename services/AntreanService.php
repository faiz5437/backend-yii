<?php

namespace app\services;

use app\models\Kunjungan;
use DateTimeImmutable;
use Yii;
use yii\db\Query;

/**
 * Informasi antrean: jadwal yang bisa dipilih beserta sisa kuota, jumlah pasien menunggu,
 * dan perkiraan jam dipanggil.
 *
 * Estimasi = max(sekarang, jam mulai praktik) + (jumlah antrean di depan × durasi layanan).
 * Durasi layanan diambil dari poli bila diisi, selain itu dari pengaturan umum.
 */
final class AntreanService
{
    private const STATUS_ANTRE = [Kunjungan::STATUS_MENUNGGU, Kunjungan::STATUS_DIPANGGIL];

    /**
     * @param bool $hanyaYangMasihBuka jadwal yang jam praktiknya sudah lewat tidak ditampilkan
     */
    public function jadwalTersedia(string $tanggal, ?string $poliId = null, bool $hanyaYangMasihBuka = false): array
    {
        $hari = (int) date('N', strtotime($tanggal));

        $query = (new Query())
            ->select([
                'j.id', 'j.poli_id', 'j.dokter_id', 'j.kuota',
                'jam_mulai' => "to_char(j.jam_mulai, 'HH24:MI')",
                'jam_selesai' => "to_char(j.jam_selesai, 'HH24:MI')",
                'poli' => 'p.nama', 'kode_poli' => 'p.kode', 'dokter' => 'tm.nama', 'spesialisasi' => 'tm.spesialisasi',
                'durasi_poli' => 'p.durasi_layanan_menit',
                'terisi' => "(SELECT COUNT(*) FROM kunjungan k WHERE k.jadwal_id = j.id AND k.tanggal_kunjungan = :tanggal AND k.status <> 'batal')",
                'menunggu' => "(SELECT COUNT(*) FROM kunjungan k WHERE k.jadwal_id = j.id AND k.tanggal_kunjungan = :tanggal AND k.status = 'menunggu')",
            ])
            ->from(['j' => 'jadwal_praktik'])
            ->innerJoin(['p' => 'poli'], 'p.id = j.poli_id AND p.is_active')
            ->innerJoin(['tm' => 'tenaga_medis'], 'tm.id = j.dokter_id AND tm.is_active')
            ->where(['j.hari' => $hari, 'j.is_active' => true])
            ->andFilterWhere(['j.poli_id' => $poliId])
            ->orderBy(['p.nama' => SORT_ASC, 'j.jam_mulai' => SORT_ASC])
            ->addParams([':tanggal' => $tanggal]);

        if ($hanyaYangMasihBuka && $tanggal === date('Y-m-d')) {
            $query->andWhere(['>', 'j.jam_selesai', date('H:i:s')]);
        }

        $sekarang = date('Y-m-d H:i');

        return array_map(function (array $row) use ($tanggal, $sekarang) {
            $durasi = $this->durasi($row['durasi_poli']);
            $estimasi = $this->hitungEstimasi($tanggal, $row['jam_mulai'], (int) $row['menunggu'], $durasi)->format('H:i');
            unset($row['durasi_poli']);

            return $row + [
                'sisa_kuota' => max(0, $row['kuota'] - $row['terisi']),
                'durasi_menit' => $durasi,
                'estimasi_panggil' => $estimasi,
                'praktik_selesai' => "{$tanggal} {$row['jam_selesai']}" <= $sekarang,
                'melewati_jam_praktik' => $estimasi >= $row['jam_selesai'],
            ];
        }, $query->all(Yii::$app->db));
    }

    /**
     * Posisi sebuah kunjungan di antrean dokternya.
     */
    public function estimasi(Kunjungan $kunjungan): array
    {
        $durasi = $this->durasi($kunjungan->poli->durasi_layanan_menit ?? null);

        $antrean = Kunjungan::find()
            ->where(['tanggal_kunjungan' => $kunjungan->tanggal_kunjungan, 'dokter_id' => $kunjungan->dokter_id, 'poli_id' => $kunjungan->poli_id]);

        $diDepan = in_array($kunjungan->status, self::STATUS_ANTRE, true)
            ? (int) (clone $antrean)->andWhere(['status' => Kunjungan::STATUS_MENUNGGU])->andWhere(['<', 'no_antrean', $kunjungan->no_antrean])->count()
            : 0;

        $dilayani = (clone $antrean)
            ->andWhere(['status' => [Kunjungan::STATUS_DIPANGGIL, Kunjungan::STATUS_DIPERIKSA]])
            ->orderBy(['dipanggil_at' => SORT_DESC, 'no_antrean' => SORT_DESC])
            ->one();

        $jamMulai = $kunjungan->jadwal ? substr($kunjungan->jadwal->jam_mulai, 0, 5) : '00:00';
        $jamSelesai = $kunjungan->jadwal ? substr($kunjungan->jadwal->jam_selesai, 0, 5) : null;
        $estimasi = $this->hitungEstimasi($kunjungan->tanggal_kunjungan, $jamMulai, $diDepan, $durasi);

        return [
            'status' => $kunjungan->status,
            'antrean_di_depan' => $diDepan,
            'sedang_dilayani' => $dilayani ? $dilayani->kodeAntrean() : null,
            'durasi_menit' => $durasi,
            'estimasi_panggil' => in_array($kunjungan->status, self::STATUS_ANTRE, true) ? $estimasi->format('H:i') : null,
            'estimasi_panggil_at' => in_array($kunjungan->status, self::STATUS_ANTRE, true) ? $estimasi->format(DATE_ATOM) : null,
            'jam_praktik' => $jamSelesai ? "{$jamMulai} - {$jamSelesai}" : null,
            'melewati_jam_praktik' => $jamSelesai !== null && $estimasi->format('H:i') >= $jamSelesai,
        ];
    }

    private function hitungEstimasi(string $tanggal, string $jamMulai, int $diDepan, int $durasi): DateTimeImmutable
    {
        $mulai = new DateTimeImmutable("{$tanggal} {$jamMulai}");
        $sekarang = new DateTimeImmutable();
        $dasar = $mulai > $sekarang ? $mulai : $sekarang;

        return $dasar->modify('+' . ($diDepan * $durasi) . ' minutes');
    }

    private function durasi(mixed $durasiPoli): int
    {
        return $durasiPoli ? (int) $durasiPoli : (int) PengaturanService::get(PengaturanService::DURASI_LAYANAN);
    }
}
