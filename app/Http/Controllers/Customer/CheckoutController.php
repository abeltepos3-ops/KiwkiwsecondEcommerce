<?php

namespace App\Http\Controllers\Customer;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Subscription;

class CheckoutController extends Controller
{
    // Tambahkan di dalam class CheckoutController

public function storeMobile(Request $request)
{
    // Tangkap data dari Flutter
    // Sesuaikan nama kolom dengan struktur tabel orders lu
    $order = \App\Models\Order::create([
        'user_id' => $request->user_id, // ID user yang lagi login
        'total_amount' => $request->total_amount,
        'status' => 'pending', 
        'shipping_method' => $request->shipping,
        'shipping_address' => $request->alamat,
        // tambahkan field lain yang ada di tabel lu
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Checkout berhasil masuk ke database Admin!',
        'order_id' => $order->id
    ], 200);
}
    public function index(Request $request)
    {
        // Ambil cart IDs dari request (jika ada), jika tidak ambil semua
        $cartIds = $request->input('cart_ids', []);
        
        if (empty($cartIds)) {
            return redirect()->route('cart.index')->with('error', 'Pilih minimal satu item untuk checkout.');
        }

        // Ambil hanya cart yang dipilih dan milik user
        $carts = Cart::where('user_id', auth()->user()->id)
                    ->whereIn('id', $cartIds)
                    ->with('product')
                    ->get();
                    
        if ($carts->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Item yang dipilih tidak ditemukan.');
        }

        $total_price = 0;
        foreach ($carts as $cart) {
            $price_after_discount = ($cart->product->price - ($cart->product->price * $cart->product->discount / 100));
            $total_price += $price_after_discount * $cart->quantity;
        }

        $bankAccounts = \App\Models\BankAccount::where('is_active', true)->get();
        $shippingProviders = \App\Models\ShippingProvider::where('is_active', true)->get();
        return view('pages.customers.checkout.index', compact('carts', 'total_price', 'bankAccounts', 'shippingProviders', 'cartIds'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'alamat' => 'required|string',
            'detail-alamat' => 'nullable|string',
            'kota' => 'required|string',
            'provinsi' => 'required|string',
            'kode-pos' => 'required|integer',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'shipping_provider_id' => 'required|exists:shipping_providers,id',
            'bukti-pembayaran' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10024',
        ], [
            'alamat.required' => 'Alamat harus diisi.',
            'detail-alamat.string' => 'Detail alamat harus berupa teks.',
            'kota.required' => 'Kota harus diisi.',
            'provinsi.required' => 'Provinsi harus diisi.',
            'kode-pos.required' => 'Kode pos harus diisi.',
            'bank_account_id.required' => 'Pilih bank untuk pembayaran.',
            'shipping_provider_id.required' => 'Pilih ekspedisi pengiriman.',
            'bukti-pembayaran.required' => 'Bukti pembayaran harus diupload.',
            'bukti-pembayaran.file' => 'Bukti pembayaran harus berupa file.',
            'bukti-pembayaran.mimes' => 'Bukti pembayaran harus berupa file JPEG, PNG, JPG, atau PDF.',
            'bukti-pembayaran.max' => 'Bukti pembayaran tidak boleh lebih dari 10 MB.',
        ]);

        $validatedData['alamat'] = implode(', ', array_filter([
            $validatedData['alamat'],
            $request->input('detail-alamat'),
            $validatedData['kota'],
            $validatedData['provinsi'],
            $validatedData['kode-pos'],
        ]));

        if ($request->hasFile('bukti-pembayaran')) {
            $file = $request->file('bukti-pembayaran');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('bukti-pembayaran', $fileName, 'public');
            $validatedData['bukti_pembayaran'] = str_replace('public/', '', $filePath);
        }

        // Ambil hanya cart yang dipilih dan milik user
        $carts = Cart::where('user_id', auth()->user()->id)
                    ->whereIn('id', $validatedData['cart_ids'])
                    ->with('product')
                    ->get();

        if ($carts->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Item yang dipilih tidak ditemukan.');
        }

        $total_price = 0;
        foreach ($carts as $cart) {
            $price_after_discount = ($cart->product->price - ($cart->product->price * $cart->product->discount / 100));
            $total_price += $price_after_discount * $cart->quantity;
        }

        $discounts = ['basic' => 5, 'premium' => 15, 'eksklusif' => 20];
        $paket = Subscription::where('user_id', auth()->user()->id)->value('subscriptions_type');
        $discount = $discounts[$paket] ?? 0;

        // Hitung shipping cost
        $shippingProvider = \App\Models\ShippingProvider::findOrFail($validatedData['shipping_provider_id']);
        $shippingRate = \App\Models\ShippingRate::where('shipping_provider_id', $shippingProvider->id)
            ->where('province', $validatedData['provinsi'])
            ->first();
        
        $shippingCost = $shippingRate ? $shippingRate->rate : $shippingProvider->base_price;
        $subtotal = $total_price - ($total_price * $discount / 100);

        $orders = new Order();
        $orders->user_id = auth()->user()->id;
        $orders->total_price = $subtotal + $shippingCost;
        $orders->shipping_address = $validatedData['alamat'];
        $orders->bank_account_id = $validatedData['bank_account_id'];
        $orders->shipping_provider_id = $validatedData['shipping_provider_id'];
        $orders->shipping_cost = $shippingCost;
        $orders->status = 'pending';
        $orders->save();

        foreach ($carts as $cart) {
            $order_items = new OrderItem();
            $order_items->order_id = $orders->id;
            $order_items->product_id = $cart->product->id;
            $order_items->quantity = $cart->quantity;
            $order_items->price = $cart->product->price - ($cart->product->price * $cart->product->discount / 100);
            $order_items->total = $order_items->price * $order_items->quantity;
            $order_items->save();
        }

        $payment = new Payment();
        $payment->order_id = $orders->id;
        $payment->receipt_image = $validatedData['bukti_pembayaran'];
        $payment->payment_status = 'pending';
        $payment->transactions_date = now();
        $payment->save();

        // Hapus hanya cart yang terpilih (yang sudah di-checkout)
        Cart::whereIn('id', $validatedData['cart_ids'])->delete();

        return redirect()->route('orders.index')->with('success', 'Pembayaran berhasil dikirimkan.');
    }
    
    public function buy_now($slug)
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        $bankAccounts = \App\Models\BankAccount::where('is_active', true)->get();
        $shippingProviders = \App\Models\ShippingProvider::where('is_active', true)->get();
        return view('pages.customers.checkout.buy-now', compact('product', 'bankAccounts', 'shippingProviders'));
    }

    public function buy_now_store(Request $request, $slug)
    {
        $validatedData = $request->validate([
            'alamat' => 'required|string',
            'detail-alamat' => 'nullable|string',
            'kota' => 'required|string',
            'provinsi' => 'required|string',
            'kode-pos' => 'required|integer',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'shipping_provider_id' => 'required|exists:shipping_providers,id',
            'bukti-pembayaran' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10024',
        ], [
            'alamat.required' => 'Alamat harus diisi.',
            'detail-alamat.string' => 'Detail alamat harus berupa teks.',
            'kota.required' => 'Kota harus diisi.',
            'provinsi.required' => 'Provinsi harus diisi.',
            'kode-pos.required' => 'Kode pos harus diisi.',
            'bank_account_id.required' => 'Pilih bank untuk pembayaran.',
            'shipping_provider_id.required' => 'Pilih ekspedisi pengiriman.',
            'bukti-pembayaran.required' => 'Bukti pembayaran harus diupload.',
            'bukti-pembayaran.file' => 'Bukti pembayaran harus berupa file.',
            'bukti-pembayaran.mimes' => 'Bukti pembayaran harus berupa file JPEG, PNG, JPG, atau PDF.',
            'bukti-pembayaran.max' => 'Bukti pembayaran tidak boleh lebih dari 10 MB.',
        ]);

        $product = Product::where('slug', $slug)->firstOrFail();

        $validatedData['alamat'] = implode(', ', array_filter([
            $validatedData['alamat'],
            $request->input('detail-alamat'),
            $validatedData['kota'],
            $validatedData['provinsi'],
            $validatedData['kode-pos'],
        ]));

        if ($request->hasFile('bukti-pembayaran')) {
            $file = $request->file('bukti-pembayaran');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('bukti-pembayaran', $fileName, 'public');
            $validatedData['bukti_pembayaran'] = str_replace('public/', '', $filePath);
        }

        $discounts = ['basic' => 5, 'premium' => 15, 'eksklusif' => 20];
        $paket = Subscription::where('user_id', auth()->user()->id)->value('subscriptions_type');
        $discount = $discounts[$paket] ?? 0;

        $totalPrice = $product->price - ($product->price * $product->discount / 100);
        $totalPrice -= $totalPrice * $discount / 100;
        
        // Hitung shipping cost
        $shippingProvider = \App\Models\ShippingProvider::findOrFail($validatedData['shipping_provider_id']);
        $shippingRate = \App\Models\ShippingRate::where('shipping_provider_id', $shippingProvider->id)
            ->where('province', $validatedData['provinsi'])
            ->first();
        
        $shippingCost = $shippingRate ? $shippingRate->rate : $shippingProvider->base_price;
        $totalPrice += $shippingCost;

        $orders = new Order();
        $orders->user_id = auth()->user()->id;
        $orders->total_price = $totalPrice;
        $orders->shipping_address = $validatedData['alamat'];
        $orders->bank_account_id = $validatedData['bank_account_id'];
        $orders->shipping_provider_id = $validatedData['shipping_provider_id'];
        $orders->shipping_cost = $shippingCost;
        $orders->status = 'pending';
        $orders->save();
        
        $order_items = new OrderItem();
        $order_items->order_id = $orders->id;
        $order_items->product_id = $product->id;
        $order_items->quantity = 1;
        $order_items->price = $product->price - ($product->price * $product->discount / 100);
        $order_items->total = $order_items->price * $order_items->quantity;
        $order_items->save();
        
        $payment = new Payment();
        $payment->order_id = $orders->id;
        $payment->receipt_image = $validatedData['bukti_pembayaran'];
        $payment->payment_status = 'pending';
        $payment->transactions_date = now();
        $payment->save();

        return redirect()->route('orders.index')->with('success', 'Pembayaran berhasil dikirimkan.');
    }
}
