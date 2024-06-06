<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    public function login(Request $request)
    {
        $this->validate($request, [
            'username' => 'required',
            'password' => 'required',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        $user = DB::table('pegawai')->where('kd_peg', $username)->first();

        if ($user) {
            if ($user->password != '') {
                $hashed_password = md5($password);
                if ($user->password === $hashed_password) {
                    $token = bin2hex(random_bytes(16));
                    DB::table('pegawai')->where('kd_peg', $username)->update(['token' => $token]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Login Berhasil',
                        'token' => $token,
                        'Data' => [
                            'kd_peg' => $user->kd_peg,
                            'nama' => $user->nama_lengkap,
                        ]
                    ]);
                } else {
                    return response()->json(['status' => 'error', 'message' => 'Password Salah'], 401);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'Pegawai belum memiliki password silahkan registrasi'], 403);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'Kode Pegawai Tidak Ditemukan'], 404);
        }
    }

    public function updatePassword(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'kd_peg' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak lengkap'
            ], 400);
        }

        $kdPegawai = $request->input('kd_peg');
        $newPassword = $request->input('password');

        // Cek apakah kd_peg ada di database
        $pegawai = DB::table('pegawai')->where('kd_peg', $kdPegawai)->first();

        if ($pegawai) {
            // Cek apakah pegawai sudah memiliki password
            if (!empty($pegawai->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pegawai sudah memiliki password'
                ], 409);
            }
            // Enkripsi password baru
            $encryptedPassword = md5($newPassword);

            // Update password di database
            $update = DB::table('pegawai')
                ->where('kd_peg', $kdPegawai)
                ->update(['password' => $encryptedPassword]);

            if ($update) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Password berhasil dibuat'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membuat password'
                ], 500);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode Pegawai Tidak Ditemukan'
            ], 404);
        }
    }

    public function cekPegawai(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kd_peg' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode Pegawai Harus Di Isi'
            ], 400);
        }

        $kdPegawai = $request->input('kd_peg');

        $pegawai = DB::table('pegawai')->where('kd_peg', $kdPegawai)->first();

        if ($pegawai) {
            $nama = $pegawai->nama_lengkap;

            if (!empty($pegawai->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pegawai sudah memiliki password'
                ], 403);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data Berhasil Di Temukan',
                    'Data' => [
                        'kd_peg' => $pegawai->kd_peg,
                        'nama' => $nama
                    ]
                ], 200);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode Tidak Ditemukan'
            ], 404);
        }
    }
}