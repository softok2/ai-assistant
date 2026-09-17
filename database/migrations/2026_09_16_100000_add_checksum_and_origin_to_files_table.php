<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('bytes')->comment('sha256 del contenido');
            $table->string('origin', 20)->default('pentaho')->after('group');
            $table->index(['project', 'group', 'expired_at'], 'files_project_group_expired_index');
        });

        // Las subidas manuales guardaban project = 'manual'; el club es ccm,
        // el único que existía. Y el origen se deduce del nombre como hacía
        // AssistantSourcesQuery hasta ahora.
        DB::table('files')->where('project', 'manual')->update(['project' => 'ccm']);
        DB::table('files')->where('name', 'like', '%-manual-%')->update(['origin' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_project_group_expired_index');
            $table->dropColumn(['checksum', 'origin']);
        });
    }
};
