<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            /*
             | When this run was undone, if it was.
             |
             | The run is NOT deleted when it is reverted. A run is the record of something that
             | happened, and "we imported 10 parts and then took them out again" is a more useful
             | history than a gap. It also stops the button firing twice, which on a second press
             | would try to delete products that are already gone.
            */
            $table->timestamp('reverted_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn('reverted_at');
        });
    }
};
