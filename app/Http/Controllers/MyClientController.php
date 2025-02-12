<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MyClient;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class MyClientController extends Controller
{
    public function index()
    {
        $clients = MyClient::all();
        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100|unique:my_client,slug',
            'is_project' => 'required|in:0,1',
            'self_capture' => 'required|in:0,1',
            'client_prefix' => 'required|string|max:4',
            'client_logo' => 'nullable|image|max:2048',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('client_logo')) {
            $path = $request->file('client_logo')->store('client_logos', 's3');
            $data['client_logo'] = Storage::disk('s3')->url($path);
        }

        $client = MyClient::create($data);

        Redis::set("client:{$client->slug}", json_encode($client), 'EX', 3600);

        return response()->json($client, 201);
    }

    public function show($slug)
    {
        $cachedClient = Redis::get("client:$slug");
        if ($cachedClient) {
            return response()->json(json_decode($cachedClient, true));
        }

        $client = MyClient::where('slug', $slug)->firstOrFail();

        Redis::set("client:$slug", json_encode($client), 'EX', 3600);

        return response()->json($client);
    }

    public function update(Request $request, $slug)
    {
        $client = MyClient::where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => 'nullable|string|max:250',
            'is_project' => 'nullable|in:0,1',
            'self_capture' => 'nullable|in:0,1',
            'client_prefix' => 'nullable|string|max:4',
            'client_logo' => 'nullable|image|max:2048',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('client_logo')) {
            $path = $request->file('client_logo')->store('client_logos', 's3');
            $data['client_logo'] = Storage::disk('s3')->url($path);
        }

        $client->update($data);

        Redis::del("client:$slug");
        Redis::set("client:$slug", json_encode($client), 'EX', 3600);

        return response()->json($client);
    }

    public function destroy($slug)
    {
        $client = MyClient::where('slug', $slug)->firstOrFail();
        $client->delete();

        Redis::del("client:$slug");

        return response()->json(['message' => 'Client deleted'], 200);
    }
}
