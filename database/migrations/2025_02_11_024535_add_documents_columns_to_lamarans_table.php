<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lamarans', function (Blueprint $table) {
            // Tambah kolom baru
            $table->string('surat_diterima_path')->nullable()->after('status');
            $table->string('surat_ditolak_path')->nullable()->after('surat_diterima_path');
            $table->text('catatan_revisi')->nullable()->after('surat_ditolak_path');
            $table->string('sertifikat_path')->nullable()->after('catatan_revisi');
            
            // Modifikasi kolom status yang sudah ada untuk menambah opsi 'revisi'
            DB::statement("ALTER TABLE lamarans MODIFY COLUMN status ENUM('pending', 'diterima', 'ditolak', 'revisi', 'magang_berjalan', 'magang_selesai') DEFAULT 'pending'");
        });
    }

    public function down()
    {
        Schema::table('lamarans', function (Blueprint $table) {
            // Hapus kolom jika rollback
            $table->dropColumn([
                'surat_diterima_path',
                'surat_ditolak_path',
                'catatan_revisi',
                'sertifikat_path'
            ]);
            
            // Kembalikan status ke enum original
            DB::statement("ALTER TABLE lamarans MODIFY COLUMN status ENUM('pending', 'diterima', 'ditolak', 'magang_berjalan', 'magang_selesai') DEFAULT 'pending'");
        });
    }
};