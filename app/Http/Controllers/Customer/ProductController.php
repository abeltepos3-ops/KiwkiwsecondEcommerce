<?php

namespace App\Http\Controllers\Customer;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    
    public function index()
    {
        $products = Product::with('category')->paginate(8);
        $featuredCollections = \App\Models\FeaturedCollection::where('is_active', true)
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('pages.customers.product.index', compact('products', 'featuredCollections'));
    }

    // Fungsi untuk API Flutter
    public function getMobileProducts() {
    $products = \App\Models\Product::all();

    $products->transform(function ($product) {
        // Ambil path gambar asli
        $path = storage_path('app/public/' . $product->image);
        
        // Konversi ke Base64 kalau filenya ada
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            $product->image_base64 = $base64;
        } else {
            $product->image_base64 = null;
        }
        return $product;
    });

    return response()->json($products);
}
}
