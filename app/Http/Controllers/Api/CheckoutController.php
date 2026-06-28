// app/Http/Controllers/Api/CheckoutController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi data yang dikirim dari mobile
        // 2. Simpan ke tabel orders
        $order = Order::create([
            'user_id' => $request->user_id,
            'total_amount' => $request->total_amount,
            'status' => 'pending',
            // ... field lain sesuai migrasi database Anda
        ]);

        // 3. Simpan item ke tabel order_items (jika ada)
        foreach($request->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }

        // Jika berhasil, data ini akan otomatis muncul di halaman dashboard Admin Web Anda
        return response()->json([
            'message' => 'Checkout Berhasil!',
            'order_id' => $order->id
        ], 200);
    }
}