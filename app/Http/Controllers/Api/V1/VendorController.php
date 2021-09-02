<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\RestaurantLogic;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Order;
use App\Models\RestaurantWallet;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

class VendorController extends Controller
{
    public function get_profile(Request $request)
    {
        $vendor = Vendor::with('restaurants')->where(['auth_token' => $request['token']])->first();
        $restaurant = Helpers::restaurant_data_formatting($vendor->restaurants[0], false);
        $order_count=$restaurant->orders->count();
        if($restaurant->orders)
        {
            unset($restaurant['orders']);
        }
        $vendor["restaurants"] = $restaurant;
        $vendor['order_count'] =$order_count;
        $vendor['member_since_days'] =$vendor->created_at->diffInDays();


        return response()->json($vendor, 200);
    }

    public function get_earning_data(Request $request)
    {
        $vendor = Vendor::with('restaurants')->where(['auth_token' => $request['token']])->first();
        $data= RestaurantLogic::get_earning_data($vendor->id);
        return response()->json($data, 200);
    }

    public function update_profile(Request $request)
    {
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|unique:vendors,email,'.$vendor->id,
            'password'=>'nullable|min:6',
        ], [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image = $request->file('image');

        if ($request->has('image')) {
            $imageName = Helpers::update('vendor/', $vendor->image, 'png', $request->file('image'));
        } else {
            $imageName = $vendor->image;
        }

        if ($request['password'] != null && strlen($request['password']) > 5) {
            $pass = bcrypt($request['password']);
        } else {
            $pass = $vendor->password;
        }
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->image = $imageName;
        $vendor->password = $pass;
        $vendor->updated_at = now();
        $vendor->save();

        return response()->json(['message' => 'successfully updated!'], 200);
    }

    public function get_current_orders(Request $request)
    {
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();
        $orders = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->whereIn('order_status', ['confirmed', 'processing', 'accepted'])
        ->where('delivery_man_id', '!=', 'null')  
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }
    
    public function get_latest_orders(Request $request)
    {
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $orders = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->whereIn('order_status', ['confirmed'])
        ->OrderScheduledIn(30)
        ->where('delivery_man_id', '!=', 'null')
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function update_order_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:confirmed,processing,handover,delivered'
        ]);

        $validator->sometimes('otp', 'required', function ($request) {
            return (Config::get('order_delivery_verification')==1 && $request['status']=='delivered');
        });

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $order = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->where('id', $request['order_id'])
        ->first();
        
        if($order->picked_up != null)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'status', 'message' => trans('messages.You_can_not_change_status_after_picked_up_by_delivery_man')]
                ]
            ], 401);
        }

        if($request['status']=='delivered' && $order->order_type != 'take_away')
        {
            return response()->json([
                'errors' => [
                    ['code' => 'status', 'message' => trans('messages.you_can_not_delivered_delivery_order')]
                ]
            ], 401);
        }
        if(Config::get('order_delivery_verification')==1 && $request['status']=='delivered' && $order->otp != $request['otp'])
        {
            return response()->json([
                'errors' => [
                    ['code' => 'otp', 'message' => 'Not matched']
                ]
            ], 401);
        }

        if ($request->status == 'delivered' && $order->transaction == null) {
            $ol = OrderLogic::create_transaction($order,'restaurant', null);
            $order->payment_status = 'paid';
        } 

        if($request->status == 'delivered')
        {
            $order->details->each(function($item, $key){
                if($item->food)
                {
                    $item->food->increment('order_count');
                }
            });
            $order->customer->increment('order_count');
        }

        $order->order_status = $request['status'];
        $order[$request['status']] = now();
        $order->save();

        $fcm_token=$order->customer->cm_firebase_token;

        $value = Helpers::order_status_update_message($request['status']);

        try {
            if ($value){
                $data=[
                    'title'=>'Order',
                    'description'=>$value,
                    'order_id'=>$order['id'],
                    'image'=>'',
                    'type'=>'order_status'
                ];
                // Helpers::send_push_notif_to_device($vendor->firebase_token,$data);

                // DB::table('user_notifications')->insert([
                //     'data'=> json_encode($data),
                //     'vendor_id'=>$vendor->id,
                //     'created_at'=>now(),
                //     'updated_at'=>now()
                // ]);

                Helpers::send_push_notif_to_device($fcm_token,$data);

                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'user_id'=>$order->customer->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }
        } catch (\Exception $e) {

        }

        return response()->json(['message' => 'Status updated'], 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $order = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->with(['details'])
        ->where('id', $request['order_id'])
        ->first();
        $details = $order->details;

        $details = Helpers::order_details_data_formatting($details);
        return response()->json($details, 200);
    }

    public function get_all_orders(Request $request)
    {
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $orders = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->where('delivery_man_id', '!=', 'null')  
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function order_payment_status_update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:paid'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $order = Order::whereHas('restaurant.vendor', function($query) use($vendor){
            $query->where('id', $vendor->id);
        })
        ->where('id', $request['order_id'])
        ->first();
        if ($order) {
            $order->payment_status = 'paid';
            $order->save();
            return response()->json(['message' => 'Payment status updated'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'not found!']
            ]
        ], 404);
    }

    public function update_fcm_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        Vendor::where(['id' => $vendor['id']])->update([
            'firebase_token' => $request['fcm_token']
        ]);

        return response()->json(['message'=>'successfully updated!'], 200);
    }

    public function get_notifications(Request $request){
        $vendor = Vendor::where(['auth_token' => $request['token']])->first();

        $notifications = Notification::active()->where(function($q) use($vendor){
            $q->whereNull('zone_id')->orWhere('zone_id', $vendor->restaurants[0]->zone_id);
        })->where('tergat', 'restaurant')->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();

        $notifications->append('data');

        $user_notifications = UserNotification::where('vendor_id', $vendor->id)->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();
        
        $notifications =  $notifications->merge($user_notifications);

        try {
            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }
}
