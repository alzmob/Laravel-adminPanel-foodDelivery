<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\Zone;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function configuration()
    {
        $currency_symbol = Currency::where(['currency_code' => Helpers::currency_code()])->first()->currency_symbol;
        $cod = json_decode(BusinessSetting::where(['key' => 'cash_on_delivery'])->first()->value, true);
        $digital_payment = json_decode(BusinessSetting::where(['key' => 'digital_payment'])->first()->value, true);
        $default_location=\App\Models\BusinessSetting::where('key','default_location')->first();
        $default_location=$default_location->value?json_decode($default_location->value, true):0;
        // $dp = json_decode(BusinessSetting::where(['key' => 'digital_payment'])->first()->value, true);
        return response()->json([
            'business_name' => BusinessSetting::where(['key' => 'business_name'])->first()->value,
            // 'business_open_time' => BusinessSetting::where(['key' => 'business_open_time'])->first()->value,
            // 'business_close_time' => BusinessSetting::where(['key' => 'business_close_time'])->first()->value,
            'logo' => BusinessSetting::where(['key' => 'logo'])->first()->value,
            'address' => BusinessSetting::where(['key' => 'address'])->first()->value,
            'phone' => BusinessSetting::where(['key' => 'phone'])->first()->value,
            'email' => BusinessSetting::where(['key' => 'email_address'])->first()->value,
            // 'restaurant_location_coverage' => Branch::where(['id'=>1])->first(['longitude','latitude','coverage']),
            // 'minimum_order_value' => (float)BusinessSetting::where(['key' => 'minimum_order_value'])->first()->value,
            'base_urls' => [
                'product_image_url' => asset('storage/app/public/product'),
                'customer_image_url' => asset('storage/app/public/profile'),
                'banner_image_url' => asset('storage/app/public/banner'),
                'category_image_url' => asset('storage/app/public/category'),
                'review_image_url' => asset('storage/app/public/review'),
                'notification_image_url' => asset('storage/app/public/notification'),
                'restaurant_image_url' => asset('storage/app/public/restaurant'),
                'vendor_image_url' => asset('storage/app/public/vendor'),
                'restaurant_cover_photo_url' => asset('storage/app/public/restaurant/cover'),
                'delivery_man_image_url' => asset('storage/app/public/delivery-man'),
                'chat_image_url' => asset('storage/app/public/conversation'),
                'campaign_image_url' => asset('storage/app/public/campaign'),
                'business_logo_url' => asset('storage/app/public/business')
            ],
            'country' => BusinessSetting::where(['key' => 'country'])->first()->value,
            'default_location'=> [ 'lat'=> $default_location?$default_location['lat']:'23.757989', 'lng'=> $default_location?$default_location['lng']:'90.360587' ],
            'currency_symbol' => $currency_symbol,
            'currency_symbol_direction' => BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value,
            'app_minimum_version' => (float)BusinessSetting::where(['key' => 'app_minimum_version'])->first()->value,
            'app_url' => BusinessSetting::where(['key' => 'app_url'])->first()->value,
            'customer_verification' => (boolean)BusinessSetting::where(['key' => 'customer_verification'])->first()->value,
            'schedule_order' => (boolean)BusinessSetting::where(['key' => 'schedule_order'])->first()->value,
            'order_delivery_verification' => (boolean)BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value,
            'cash_on_delivery' => $cod['status'] == 1 ? true : false,
            'digital_payment' => $digital_payment['status'] == 1 ? true : false,
            'terms_and_conditions' => BusinessSetting::where(['key' => 'terms_and_conditions'])->first()->value,
            'privacy_policy' => BusinessSetting::where(['key' => 'privacy_policy'])->first()->value,
            'about_us' => BusinessSetting::where(['key' => 'about_us'])->first()->value,
            'per_km_shipping_charge' => (double)BusinessSetting::where(['key' => 'per_km_shipping_charge'])->first()->value,
            'minimum_shipping_charge' => (double)BusinessSetting::where(['key' => 'minimum_shipping_charge'])->first()->value,
            'demo'=>env('APP_MODE')=='demo'?true:false,
        ]);
    }

    public function get_zone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $point = new Point($request->lat,$request->lng);
        $zone = Zone::contains('coordinates', $point)->first();
        if($zone)
        {
            if($zone->status)
            {
                return response()->json(['zone_id'=>$zone->id], 200);
            }
            return response()->json(['message'=>trans('messages.we_are_temporarily_unavailable_in_this_area')], 403);
        }
        return response()->json(['message'=>trans('messages.service_not_available_in_this_area')], 404);
    }
}
