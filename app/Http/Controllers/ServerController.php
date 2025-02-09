<?php

namespace App\Http\Controllers;

use App\Models\IPs;
use App\Models\Labels;
use App\Models\Pricing;
use App\Models\Server;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{

    public function index()
    {
        $servers = Server::allActiveServers();
        $non_active_servers = Server::allNonActiveServers();
        return view('servers.index', compact(['servers', 'non_active_servers']));
    }

    public function showServersPublic()
    {
        $settings = Settings::getSettings();
        Settings::setSettingsToSession($settings);

        if ((Session::get('show_servers_public') === 1)) {
            $servers = Server::allPublicServers();
            return view('servers.public-index', compact('servers'));
        }
        abort(404);
    }

    public function create()
    {
        return view('servers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'hostname' => 'required|min:5',
            'ip1' => 'sometimes|nullable|ip',
            'ip2' => 'sometimes|nullable|ip',
            'ns1' => 'sometimes|nullable|string',
            'ns2' => 'sometimes|nullable|string',
            'server_type' => 'integer',
            'ssh_port' => 'integer',
            'bandwidth' => 'integer',
            'ram' => 'required|numeric',
            'disk' => 'required|integer',
            'os_id' => 'required|integer',
            'provider_id' => 'required|integer',
            'location_id' => 'required|integer',
            'price' => 'required|numeric',
            'cpu' => 'required|integer',
            'was_promo' => 'integer',
            'next_due_date' => 'required|date',
            'owned_since' => 'sometimes|nullable|date',
            'label1' => 'sometimes|nullable|string',
            'label2' => 'sometimes|nullable|string',
            'label3' => 'sometimes|nullable|string',
            'label4' => 'sometimes|nullable|string',
        ]);

        $server_id = Str::random(8);

        $pricing = new Pricing();
        $pricing->insertPricing(1, $server_id, $request->currency, $request->price, $request->payment_term, $request->next_due_date);

        if (!is_null($request->ip1)) {
            IPs::insertIP($server_id, $request->ip1);
        }

        if (!is_null($request->ip2)) {
            IPs::insertIP($server_id, $request->ip2);
        }

        Server::create([
            'id' => $server_id,
            'hostname' => $request->hostname,
            'server_type' => $request->server_type,
            'os_id' => $request->os_id,
            'ssh' => $request->ssh_port,
            'provider_id' => $request->provider_id,
            'location_id' => $request->location_id,
            'ram' => $request->ram,
            'ram_type' => $request->ram_type,
            'ram_as_mb' => ($request->ram_type === 'MB') ? $request->ram : ($request->ram * 1024),
            'disk' => $request->disk,
            'disk_type' => $request->disk_type,
            'disk_as_gb' => ($request->disk_type === 'GB') ? $request->disk : ($request->disk * 1024),
            'owned_since' => $request->owned_since,
            'ns1' => $request->ns1,
            'ns2' => $request->ns2,
            'bandwidth' => $request->bandwidth,
            'cpu' => $request->cpu,
            'was_promo' => $request->was_promo,
            'show_public' => (isset($request->show_public)) ? 1 : 0
        ]);

        Labels::insertLabelsAssigned([$request->label1, $request->label2, $request->label3, $request->label4], $server_id);

        Server::serverRelatedCacheForget();

        return redirect()->route('servers.index')
            ->with('success', 'Server Created Successfully.');
    }

    public function show(Server $server)
    {
        $server_data = Server::server($server->id);

        return view('servers.show', compact(['server_data']));
    }

    public function edit(Server $server)
    {
        $server_data = Server::server($server->id);

        return view('servers.edit', compact(['server_data']));
    }

    public function update(Request $request, Server $server)
    {
        $request->validate([
            'hostname' => 'required|min:5',
            'ip1' => 'sometimes|nullable|ip',
            'ip2' => 'sometimes|nullable|ip',
            'ns1' => 'sometimes|nullable|string',
            'ns2' => 'sometimes|nullable|string',
            'server_type' => 'integer',
            'ssh_port' => 'integer',
            'bandwidth' => 'integer',
            'ram' => 'required|numeric',
            'disk' => 'required|integer',
            'os_id' => 'required|integer',
            'provider_id' => 'required|integer',
            'location_id' => 'required|integer',
            'price' => 'required|numeric',
            'cpu' => 'required|integer',
            'was_promo' => 'integer',
            'next_due_date' => 'required|date',
            'owned_since' => 'sometimes|nullable|date',
            'label1' => 'sometimes|nullable|string',
            'label2' => 'sometimes|nullable|string',
            'label3' => 'sometimes|nullable|string',
            'label4' => 'sometimes|nullable|string',
        ]);

        $server->update([
            'hostname' => $request->hostname,
            'server_type' => $request->server_type,
            'os_id' => $request->os_id,
            'ssh' => $request->ssh_port,
            'provider_id' => $request->provider_id,
            'location_id' => $request->location_id,
            'ram' => $request->ram,
            'ram_type' => $request->ram_type,
            'ram_as_mb' => ($request->ram_type === 'MB') ? $request->ram : ($request->ram * 1024),
            'disk' => $request->disk,
            'disk_type' => $request->disk_type,
            'disk_as_gb' => ($request->disk_type === 'GB') ? $request->disk : ($request->disk * 1024),
            'owned_since' => $request->owned_since,
            'ns1' => $request->ns1,
            'ns2' => $request->ns2,
            'bandwidth' => $request->bandwidth,
            'cpu' => $request->cpu,
            'was_promo' => $request->was_promo,
            'active' => (isset($request->is_active)) ? 1 : 0,
            'show_public' => (isset($request->show_public)) ? 1 : 0
        ]);

        $pricing = new Pricing();
        $pricing->updatePricing($server->id, $request->currency, $request->price, $request->payment_term, $request->next_due_date);

        Labels::deleteLabelsAssignedTo($server->id);

        Labels::insertLabelsAssigned([$request->label1, $request->label2, $request->label3, $request->label4], $server->id);

        IPs::deleteIPsAssignedTo($server->id);

        for ($i = 1; $i <= 8; $i++) {//Max of 8 ips
            $obj = 'ip' . $i;
            if (isset($request->$obj) && !is_null($request->$obj)) {
                IPs::insertIP($server->id, $request->$obj);
            }
        }

        Server::serverRelatedCacheForget();
        Server::serverSpecificCacheForget($server->id);

        return redirect()->route('servers.index')
            ->with('success', 'Server Updated Successfully.');
    }

    public function destroy(Server $server)
    {
        if ($server->delete()) {
            $p = new Pricing();
            $p->deletePricing($server->id);

            Labels::deleteLabelsAssignedTo($server->id);

            IPs::deleteIPsAssignedTo($server->id);

            Server::serverRelatedCacheForget();

            return redirect()->route('servers.index')
                ->with('success', 'Server was deleted Successfully.');
        }

        return redirect()->route('servers.index')
            ->with('error', 'Server was not deleted.');
    }

    public function chooseCompare()
    {//NOTICE: Selecting servers is not cached yet
        $all_servers = Server::where('has_yabs', 1)->get();

        if (isset($all_servers[1])) {
            return view('servers.choose-compare', compact('all_servers'));
        }

        return redirect()->route('servers.index')
            ->with('error', 'You need atleast 2 servers with a YABS to do a compare');
    }

    public function compareServers($server1, $server2)
    {
        $server1_data = Server::server($server1);

        if (!isset($server1_data[0]->yabs[0])) {
            abort(404);
        }

        $server2_data = Server::server($server2);

        if (!isset($server2_data[0]->yabs[0])) {
            abort(404);
        }
        return view('servers.compare', compact('server1_data', 'server2_data'));
    }
}
