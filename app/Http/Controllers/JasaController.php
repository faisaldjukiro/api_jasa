<?php

namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JasaController extends BaseController
{
    public function getJasa(Request $request)
    {
        $token = $request->header('token');
        $kd_peg = $request->input('kd_peg', '');

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak tersedia'], 400);
        }

        if (empty($kd_peg)) {
            return response()->json(['status' => 'error', 'message' => 'Kode pegawai tidak tersedia'], 400);
        }

        $kd_peg = addslashes($kd_peg); // Escaping for security

        $query = "
            SELECT jasa.id_jasa,
                   LEFT(jasa.blntahun, 2) AS bulan,
                   RIGHT(jasa.blntahun, 4) AS tahun,
                   jasa.blntahun,
                   jasa.jumlah, 
                   jasa.status 
            FROM pegawai 
            INNER JOIN jasa ON jasa.kd_peg = pegawai.kd_peg 
            WHERE pegawai.token = ? 
            AND pegawai.kd_peg = ? 
            ORDER BY id_jasa DESC";

        $results = DB::select($query, [$token, $kd_peg]);

        if ($results) {
            foreach ($results as $row) {
                $row->bulan = $this->convertBulan($row->bulan);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil ditemukan',
                'data' => $results
            ], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Data Tidak Ditemukan'], 404);
        }
    }

    private function convertBulan($angka)
    {
        $bulan = [
            "01" => "Januari",
            "02" => "Februari",
            "03" => "Maret",
            "04" => "April",
            "05" => "Mei",
            "06" => "Juni",
            "07" => "Juli",
            "08" => "Agustus",
            "09" => "September",
            "10" => "Oktober",
            "11" => "November",
            "12" => "Desember"
        ];

        return isset($bulan[$angka]) ? $bulan[$angka] : $angka;
    }
    public function getJasaSatu(Request $request)
    {
        $token = $request->header('token');
        $kd_peg = $request->input('kd_peg', '');

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak tersedia'], 400);
        }

        if (empty($kd_peg)) {
            return response()->json(['status' => 'error', 'message' => 'Kode pegawai tidak tersedia'], 400);
        }

        $query = "
            SELECT jasa.id_jasa, jasa.kd_peg, pegawai.nama_lengkap, jasa.jumlah, jasa.status, jasa.blntahun 
            FROM pegawai 
            INNER JOIN jasa ON jasa.kd_peg = pegawai.kd_peg 
            WHERE pegawai.token = ? AND pegawai.kd_peg = ? 
            ORDER BY jasa.id_jasa DESC 
            LIMIT 1";

        $results = DB::select($query, [$token, $kd_peg]);

        if ($results) {
            $data = $results[0];
            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil ditemukan',
                'data' => [
                    'jumlah' => $data->jumlah,
                    'blnthn' => $data->blntahun
                ]
            ], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Data Tidak Ditemukan'], 404);
        }
    }
    public function getJasaRinciDokter(Request $request)
    {
        $token = $request->header('token');
        $kd_peg = $request->input('kd_peg', '');
        $bulan = $request->input('blnthn', '');

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak tersedia'], 400);
        }

        if (empty($kd_peg) || empty($bulan)) {
            return response()->json(['status' => 'error', 'message' => 'Kode pegawai atau bulan tidak tersedia'], 400);
        }

        $query = "
            SELECT jasa_rinci.id_rinci, jasa_rinci.kd_peg, jasa_rinci.kasus, jasa_rinci.pasien, jasa_rinci.tindakan, jasa_rinci.jumlah, jasa_rinci.blnthn, jasa_rinci.klem 
            FROM pegawai 
            INNER JOIN jasa_rinci ON jasa_rinci.kd_peg = pegawai.kd_peg 
            WHERE pegawai.token = ? 
            AND pegawai.kd_peg = ? 
            AND jasa_rinci.blnthn = ?
            ORDER BY jasa_rinci.id_rinci DESC";

        $results = DB::select($query, [$token, $kd_peg, $bulan]);

        if ($results) {
            $data = [];
            foreach ($results as $row) {
                $kasus = $row->kasus;
                $pasien = $row->pasien;
                if (!isset($data[$kasus][$pasien])) {
                    $data[$kasus][$pasien] = [];
                }
                $data_pasien = [
                    'tindakan' => $row->tindakan,
                    'jumlah' => $row->jumlah,
                    'klem' => $row->klem
                ];
                $data[$kasus][$pasien][] = $data_pasien;
            }

            $groupedData = [];
            foreach ($data as $kasus => $group) {
                $groupedKasus = [
                    'kasus' => $kasus,
                    'data_kasus' => []
                ];
                foreach ($group as $pasien => $pasienData) {
                    $groupedKasus['data_kasus'][] = [
                        'pasien' => $pasien,
                        'data_pasien' => $pasienData
                    ];
                }
                $groupedData[] = $groupedKasus;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil ditemukan',
                'data' => $groupedData
            ], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Data Tidak Ditemukan'], 404);
        }
    }
    
    public function getJasaRinciPasien(Request $request)
    {
        $token = $request->header('token');

        $checkTokenResult = DB::select("SELECT * FROM pegawai WHERE token = ?", [$token]);

        if (!$token || empty($checkTokenResult)) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid'], 401);
        }

        $blnthn = $request->input('blnthn', '');
        $pasien = $request->input('pasien', '');

        if (empty($blnthn) || empty($pasien)) {
            return response()->json(['status' => 'error', 'message' => 'Parameter blnthn dan pasien tidak tersedia'], 400);
        }

        $results = DB::select("SELECT pegawai.nama_lengkap, jasa_rinci.pasien, jasa_rinci.tindakan, jasa_rinci.jumlah, jasa_rinci.klem 
                                FROM pegawai 
                                INNER JOIN jasa_rinci ON jasa_rinci.kd_peg = pegawai.kd_peg 
                                WHERE jasa_rinci.blnthn = ? AND jasa_rinci.pasien = ?", [$blnthn, $pasien]);

        if ($results) {
            $data = [];
            foreach ($results as $row) {
                $nama_lengkap = $row->nama_lengkap;
                $tindakan = $row->tindakan;
                $jumlah = $row->jumlah;
                $klem = $row->klem;

                $data_pasien = [
                    'tindakan' => $tindakan,
                    'jumlah' => $jumlah,
                    'klem' => $klem
                ];

                if (!isset($data[$nama_lengkap])) {
                    $data[$nama_lengkap] = [
                        'nama_lengkap' => $nama_lengkap,
                        'data_nama_lengkap' => []
                    ];
                }

                $data[$nama_lengkap]['data_nama_lengkap'][] = [
                    'pasien' => $pasien,
                    'data_pasien' => [$data_pasien]
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil ditemukan',
                'data' => array_values($data)
            ], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Data Tidak Ditemukan'], 404);
        }
    }

}