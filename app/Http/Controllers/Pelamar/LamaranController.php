<?php

namespace App\Http\Controllers\Pelamar;

use App\Models\Lamaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LamaranController extends Controller
{
    public function create()
    {
        $user = auth()->user()->load('biodata');
        
        // Cek lamaran terakhir user
        $lastLamaran = Lamaran::where('user_id', auth()->id())
            ->latest()
            ->first();

        // Jika ada lamaran dan statusnya pending atau diterima, redirect ke status
        if ($lastLamaran && in_array($lastLamaran->status, ['pending', 'diterima'])) {
            return redirect()->route('pelamar.status')
                ->with('info', 'Anda sudah memiliki lamaran yang sedang diproses atau telah diterima.');
        }

        return view('pelamar.formulir', compact('user', 'lastLamaran'));
    }

    public function store(Request $request)
    {
        // Cek apakah user memiliki lamaran yang pending atau diterima
        $existingLamaran = Lamaran::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'diterima'])
            ->first();

        if ($existingLamaran) {
            return redirect()->route('pelamar.status')
                ->with('error', 'Anda sudah memiliki lamaran yang sedang diproses atau telah diterima.');
        }

        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'asal_sekolah' => 'required|string|max:255',
            'jurusan' => 'required|string|max:255',
            'semester' => 'required|integer|min:1',
            'tanggal_mulai' => 'required|date|after:today',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'surat_pengantar' => 'required|file|mimes:pdf|max:2048',
            'cv' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        try {
            $suratPengantarPath = $request->file('surat_pengantar')
                ->store('surat_pengantar', 'public');

            $cvPath = null;
            if ($request->hasFile('cv')) {
                $cvPath = $request->file('cv')->store('cv', 'public');
            }

            // Jika ada lamaran sebelumnya yang ditolak atau revisi, hapus file lamanya
            $oldLamaran = Lamaran::where('user_id', auth()->id())
                ->whereIn('status', ['revisi'])
                ->latest()
                ->first();

            if ($oldLamaran) {
                if (Storage::exists($oldLamaran->surat_pengantar_path)) {
                    Storage::delete($oldLamaran->surat_pengantar_path);
                }
                if ($oldLamaran->cv_path && Storage::exists($oldLamaran->cv_path)) {
                    Storage::delete($oldLamaran->cv_path);
                }
            }

            $lamaran = Lamaran::create([
                'user_id' => auth()->id(),
                'nama' => $request->nama,
                'email' => $request->email,
                'asal_sekolah' => $request->asal_sekolah,
                'jurusan' => $request->jurusan,
                'semester' => $request->semester,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'surat_pengantar_path' => $suratPengantarPath,
                'cv_path' => $cvPath,
                'status' => 'pending'
            ]);

            return redirect()->route('pelamar.status')
                ->with('success', 'Lamaran berhasil diajukan! Silahkan pantau status lamaran Anda.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat mengajukan lamaran. Silakan coba lagi.');
        }
    }

    public function status()
    {
        $lamarans = Lamaran::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('pelamar.status', compact('lamarans'));
    }

    public function rekap()
    {
        $user = Auth::user();
        
        $lamarans = Lamaran::where('user_id', $user->id)
            ->when(request('status'), function ($query) {
                return $query->where('status', request('status'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('pelamar.rekap', compact('lamarans'));
    }

    public function detail($id)
    {
        $lamaran = Lamaran::where('user_id', auth()->id())
            ->findOrFail($id);
        
        return view('pelamar.detail-lamaran', compact('lamaran'));
    }
    public function downloadSurat($id, $type)
    {
        $lamaran = Lamaran::findOrFail($id);
        
        // Check if the logged-in user owns this application
        if ($lamaran->user_id !== auth()->id()) {
            return back()->with('error', 'Unauthorized access');
        }
    
        // Get the correct file path based on type
        $filePath = match($type) {
            'surat_pengantar' => $lamaran->surat_pengantar_path,
            'cv' => $lamaran->cv_path,
            'surat_diterima' => $lamaran->surat_diterima_path,
            'surat_ditolak' => $lamaran->surat_ditolak_path,
            'sertifikat' => $lamaran->sertifikat_path,
            default => null
        };
    
        if ($filePath && Storage::exists($filePath)) {
            return response()->download(storage_path('app/public/' . $filePath));
        }
    
        return redirect()->back()->with('error', 'File tidak ditemukan.');
    }
}