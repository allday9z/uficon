<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function getAllStore(Request $request)
    {
        // The payload has already been decrypted by SecurePayload middleware
        // and available in $request->input('decrypted_payload') or via direct access if merged.
        // But for getAllStore, we might just ignore the payload if no filter is needed.
        // However, the requirement is that payload IS encrypted.
        
        $stores = Store::with('brand')->get();
        
        return response()->json($stores, 200);
    }
}
