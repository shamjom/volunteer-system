<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class VolunteerController extends Controller
{

    //  (فلترة) عرض كل المتطوعين + بحث
public function index(Request $request)
{

    $query = User::where('role','volunteer')
    ->with('volunteerProfile');


    if($request->search){
        $query->where( 'name','like', '%'.$request->search.'%'); }

    if($request->status){
        $query->where('status',$request->status);}


    return response()->json([
        'volunteers'=>$query->get()
    ]);}

    public function show(User $user)
    {
    return response()->json([
        'volunteer'=>$user->load('volunteerProfile')]);}



    // تعديل معلومات متطوع
    public function update(Request $request, User $user)
    {
        if($user->role !== 'volunteer'){

            return response()->json([
                'message'=>'User is not volunteer' ],422);}

        $user->update([
            'name'=>$request->name,
            'email'=>$request->email,
        ]);

        $user->volunteerProfile()->updateOrCreate(
            ['user_id'=>$user->id],
            ['phone'=>$request->phone,'address'=>$request->address ] );

        return response()->json([
            'message'=>'Volunteer updated successfully',
            'user'=>$user->load('volunteerProfile')

        ]);}


    // حذف متطوع
    public function destroy(User $user)
    {
        if($user->role !== 'volunteer'){
            return response()->json([
                'message'=>'User is not volunteer'
            ],422);
        }
        $user->delete();
        return response()->json([
            'message'=>'Volunteer deleted successfully'
        ]);
    }

     //تحديث ساعات التطوع
    public function updateHours(Request $request, User $user)
    {

      $profile = $user->volunteerProfile;
      $profile->update(['total_hours'=>$request->hours]);

      return response()->json(['message'=>'Hours updated','hours'=>$profile->total_hours]);
    
    }
}
