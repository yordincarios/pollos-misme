<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Seeder;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Platos Fuertes' => [
                ['name' => 'Lomo Saltado Criollo', 'price' => 38.50, 'image' => null, 'is_available' => true],
                ['name' => 'Hamburguesa Doble Angus', 'price' => 29.00, 'image' => null, 'is_available' => true],
                ['name' => 'Pollo a la Brasa (1/4)', 'price' => 22.00, 'image' => null, 'is_available' => true],
                ['name' => 'Pasta Fettuccine Alfredo', 'price' => 32.00, 'image' => null, 'is_available' => true],
                ['name' => 'Costillas BBQ Glaseadas', 'price' => 44.00, 'image' => null, 'is_available' => true],
            ],
            'Entradas' => [
                ['name' => 'Ceviche Mixto Tradicional', 'price' => 34.00, 'image' => null, 'is_available' => true],
                ['name' => 'Tequeños de Queso (8 und)', 'price' => 18.00, 'image' => null, 'is_available' => true],
                ['name' => 'Alitas Picantes Crispy', 'price' => 24.50, 'image' => null, 'is_available' => true],
                ['name' => 'Empanadas de Carne (2 und)', 'price' => 14.00, 'image' => null, 'is_available' => true],
            ],
            'Bebidas' => [
                ['name' => 'Limonada de Hierbabuena Frozen', 'price' => 12.00, 'image' => null, 'is_available' => true],
                ['name' => 'Chicha Morada Especial (1L)', 'price' => 16.00, 'image' => null, 'is_available' => true],
                ['name' => 'Cerveza Artesanal IPA 330ml', 'price' => 15.00, 'image' => null, 'is_available' => true],
                ['name' => 'Gaseosa Inca Kola / Coca Cola', 'price' => 7.00, 'image' => null, 'is_available' => true],
                ['name' => 'Agua Mineral sin Gas 500ml', 'price' => 5.00, 'image' => null, 'is_available' => true],
            ],
            'Postres' => [
                ['name' => 'Tarta de Chocolate con Helado', 'price' => 16.50, 'image' => null, 'is_available' => true],
                ['name' => 'Cheesecake de Frutos Rojos', 'price' => 17.00, 'image' => null, 'is_available' => true],
                ['name' => 'Crema Volteada Clásica', 'price' => 13.00, 'image' => null, 'is_available' => true],
            ],
        ];

        foreach ($categories as $catName => $dishes) {
            $category = Category::firstOrCreate(['name' => $catName]);
            foreach ($dishes as $dishData) {
                Dish::firstOrCreate(
                    ['name' => $dishData['name'], 'category_id' => $category->id],
                    $dishData
                );
            }
        }
    }
}
