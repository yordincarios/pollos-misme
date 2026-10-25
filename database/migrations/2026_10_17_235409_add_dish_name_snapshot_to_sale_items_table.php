<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('dish_name')->nullable();
        });

        DB::table('sale_items')
            ->select(['id', 'dish_id'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $saleItems): void {
                $dishNames = DB::table('dishes')
                    ->whereIn('id', $saleItems->pluck('dish_id'))
                    ->pluck('name', 'id');

                foreach ($saleItems as $saleItem) {
                    if ($dishNames->has($saleItem->dish_id)) {
                        DB::table('sale_items')
                            ->where('id', $saleItem->id)
                            ->update(['dish_name' => $dishNames->get($saleItem->dish_id)]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('dish_name');
        });
    }
};
