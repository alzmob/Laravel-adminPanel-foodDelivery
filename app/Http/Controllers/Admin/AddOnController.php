<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class AddOnController extends Controller
{
    public function index()
    {
        $addons = AddOn::orderBy('name')->paginate(25);
        return view('admin-views.addon.index', compact('addons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'restaurant_id' => 'required',
            'price' => 'required',
        ], [
            'name.required' => 'Name is required!',
            'restaurant_id.required' => trans('messages.please_select_restaurant'),
        ]);

        $addon = new AddOn();
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->restaurant_id = $request->restaurant_id;
        $addon->save();
        Toastr::success('Addon added successfully!');
        return back();
    }

    public function edit($id)
    {
        $addon = AddOn::find($id);
        return view('admin-views.addon.edit', compact('addon'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'restaurant_id' => 'required',
            'price' => 'required',
        ], [
            'name.required' => 'Name is required!',
            'restaurant_id.required' => trans('messages.please_select_restaurant'),
        ]);

        $addon = AddOn::find($id);
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->restaurant_id = $request->restaurant_id;
        $addon->save();
        Toastr::success('Addon updated successfully!');
        return redirect(route('admin.addon.add-new'));
    }

    public function delete(Request $request)
    {
        $addon = AddOn::find($request->id);
        $addon->delete();
        Toastr::success('Addon removed!');
        return back();
    }

    public function status(AddOn $addon, Request $request)
    {
        $addon->status = $request->status;
        $addon->save();
        Toastr::success('Addon status updated!');
        return back();
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $addons=AddOn::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('admin-views.addon.partials._table',compact('addons'))->render()
        ]);
    }
}
