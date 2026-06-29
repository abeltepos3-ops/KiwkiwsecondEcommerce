<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ==============================================================
    // 1. FUNGSI UNTUK WEB BROWSER (Kodingan Asli Lu)
    // ==============================================================

    public function login()
    {
        return view('pages/auth/login');
    }

    public function login_post(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password harus diisi.',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            // Regenerate session ID untuk keamanan tambahan
            $request->session()->regenerate();
            
            // Simpan session untuk memastikan tersimpan
            $request->session()->save();

            // Redirect ke halaman home dengan pesan sukses
            if(Auth::user()->role == 'admin') {
                return redirect()->route('dashboard.index')->with('success', 'Anda berhasil login.');
            }
            
            return redirect()->route('home.index')->with('success', 'Anda berhasil login.');
        }

        // Kembalikan ke halaman login dengan pesan error dan input email tetap terisi
        return back()->withErrors([
            'email' => 'Kredensial yang diberikan tidak cocok dengan catatan kami.',
        ])->withInput($request->only('email'));
    }

    public function register()
    {
        return view('pages/auth/register');
    }

    public function register_post(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|unique:users',
        ], [
            'name.required' => 'Nama harus diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama tidak boleh lebih dari :max karakter.',
            'email.required' => 'Email harus diisi.',
            'email.string' => 'Email harus berupa teks.',
            'email.email' => 'Email harus dalam format yang benar.',
            'email.max' => 'Email tidak boleh lebih dari :max karakter.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password harus diisi.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password harus minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'phone.required' => 'Nomor telepon harus diisi.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.unique' => 'Nomor telepon sudah digunakan.',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ];

        $user = User::create($data);

        Auth::login($user);

        return redirect()->route('home.index')->with('success', 'Anda telah berhasil mendaftar.');
    }

    public function logout()
    {
        Auth::logout();
        
        // Regenerate session setelah logout
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }

    // ==============================================================
    // 2. FUNGSI UNTUK MOBILE APP FLUTTER (REST API)
    // ==============================================================

    public function registerMobile(Request $request)
    {
        // Validasi data dari Flutter
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        // Karena di web butuh kolom 'phone', kita buat nomor telepon dummy 
        // secara otomatis agar tidak ditolak oleh database (karena form flutter belum ada input nomor hp)
        $phone = $request->phone ?? '08' . rand(100000000, 999999999);

        // Simpan ke database users
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $phone, // Memasukkan nomor telepon
            'role' => 'customer' // Atur role default
        ]);

        // Balikin respon ke Flutter (JSON)
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil didaftarkan!',
            'data' => $user
        ], 201);
    }

    public function loginMobile(Request $request)
    {
        // Validasi input email & password dari Flutter
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // Cek apakah user terdaftar dan password-nya cocok
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password yang lu masukkan salah, mbutt!'
            ], 401);
        }

        // Balikin data user dalam bentuk JSON kalau berhasil login
        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil!',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role ?? 'customer',
            ]
        ], 200);
    }
}