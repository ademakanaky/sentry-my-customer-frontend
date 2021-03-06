<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_encode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use GuzzleHttp\Exception\RequestException;

class SettingsController extends Controller
{
    // Defining headers
    public $headers;
    public $user_id;

    // Controller action to display settings page.
    public function index()
    {

        // Setting header
        $user_details = [];
        Cookie::get('user_id') !== null ?  $user_details['id'] = Cookie::get('user_id') : "";
        Cookie::get('email') !== null ?  $user_details['email'] = Cookie::get('email') : "";
        Cookie::get('first_name') !== null ?  $user_details['first_name'] = Cookie::get('first_name') : "";
        Cookie::get('last_name') !== null ?  $user_details['last_name'] = Cookie::get('last_name') : "";
        Cookie::get('phone_number') !== null ?  $user_details['phone_number'] = Cookie::get('phone_number') : "";
        Cookie::get('is_active') !== null ?  $user_details['is_active'] = Cookie::get('is_active') : "";
        return view('backend.settings.settings')->with("user_details", $user_details);
        // Setting User_id
        $this->user_id = Cookie::get('user_id');
    }

    // Controller action to update user details.
    public function update(Request $request)
    {

        // Setting header

        // Setting User_id
        $this->user_id = Cookie::get('user_id');

        try {
            // check if all fields are available
            if ($request->all()) {
                $control = $request->input('control', '');

                if ($control == 'profile_update') {

                    $url = env('API_URL', 'https://dev.api.customerpay.me') . '/store-admin/update';
                    $client = new Client();
                    $data = [
                        "first_name" => $request->input('first_name'),
                        "last_name" => $request->input('last_name'),
                        "email" => $request->input('email')
                    ];
                    // make an api call to update the user_details
                    $this->headers = ['headers' => ['x-access-token' => Cookie::get('api_token')], 'form_params' => $data];
                    $response = $client->request('PUT', $url, $this->headers);
                } elseif ($control == 'password_change') {
                    $request->validate([
                        'current_password' => 'required|min:6',
                        'new_password' => 'required|min:6|confirmed',
                    ]);
                    $url = env('API_URL', 'https://dev.api.customerpay.me') . '/store-admin/update/password';
                    $client = new Client();
                    $data = [
                        "old_password" => $request->input('current_password'),
                        "new_password" => $request->input('new_password'),
                        "confirm_password" => $request->input('new_password_confirmation')
                    ];
                    // make an api call to update the user_details
                    $this->headers = ['headers' => ['x-access-token' => Cookie::get('api_token')], 'form_params' => $data];
                    $response = $client->request('POST', $url, $this->headers);
                } else {
                    return view('errors.404');
                }
                if ($response->getStatusCode() == 200) {
                    $request->session()->flash('alert-class', 'alert-success');
                    if ($control == 'profile_update') {
                        $user_detail_res = json_decode($response->getBody(), true);
                        $filtered_user_detail = $user_detail_res['data']['store_admin']['local'];
                        $user_details = [
                            "email" => $filtered_user_detail['email'],
                            "phone_number" => $filtered_user_detail['phone_number'],
                            "first_name" => $filtered_user_detail['first_name'],
                            "last_name" => $filtered_user_detail['last_name'],
                            "is_active" => Cookie::get('is_active')
                        ];
                        Cookie::queue('phone_number', $filtered_user_detail['phone_number']);
                        Cookie::queue('first_name', $filtered_user_detail['first_name']);
                        Cookie::queue('email', $filtered_user_detail['email']);
                        Cookie::queue('last_name', $filtered_user_detail['last_name']);
                        $request->session()->flash('message', "Profile details updated successfully");
                        return redirect()->route('setting')->with("user_details", $user_details);
                    }
                    if ($control == 'password_change') {
                        $request->session()->flash('message', "Password updated successfully");
                        return redirect()->route('change_password');
                    }
                }
            } else {
                return redirect()->route('settings');
            }
        } catch (RequestException $e) {

            if ($e->hasResponse()) {
                $response = json_decode($e->getResponse()->getBody());
                $request->session()->flash('alert-class', 'alert-danger');
                if ($response->message == 'Error updating password') {
                    $request->session()->flash('message', 'current password is incorrect');
                } else {
                    $request->session()->flash('message', $response->message);
                }
            }

            if ($control == 'password_change') {
                return redirect()->route('change_password');
            }
            if ($control == 'profile_update') {
                return redirect()->route('setting');
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return view('errors.500');
        }
    }

    public function change_password()
    {
        return view('backend.change_password.index');
    }
}
