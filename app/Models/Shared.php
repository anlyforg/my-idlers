<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Shared extends Model
{
    use HasFactory;

    public $table = 'shared_hosting';

    protected $keyType = 'string';

    protected $fillable = ['id', 'active', 'main_domain', 'has_dedicated_ip', 'ip', 'shared_type', 'provider_id', 'location_id', 'bandwidth', 'disk', 'disk_type', 'disk_as_gb', 'domains_limit', 'subdomains_limit', 'ftp_limit', 'email_limit', 'db_limit', 'was_promo', 'owned_since'];

    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $array = Settings::orderByProcess(Session::get('sort_on') ?? 2);//created_at desc if not set
            if (!in_array(Session::get('sort_on'), [3, 4, 5, 6], true)) {
                $builder->orderBy($array[0], $array[1]);
            }
        });
    }

    public static function allSharedHosting()
    {//All shared hosting and relationships (no using joins)
        return Cache::remember("all_shared", now()->addMonth(1), function () {
            $query = Shared::with(['location', 'provider', 'price', 'ips', 'labels']);
            if (in_array(Session::get('sort_on'), [3, 4, 5, 6], true)) {
                $options = Settings::orderByProcess(Session::get('sort_on'));
                $query->orderBy(Pricing::select("pricings.$options[0]")->whereColumn("pricings.service_id", "shared_hosting.id"), $options[1]);
            }
            return $query->get();
        });
    }

    public static function sharedHosting(string $shared_id)
    {//Single shared hosting and relationships (no using joins)
        return Cache::remember("shared_hosting.$shared_id", now()->addMonth(1), function () use ($shared_id) {
            return Shared::where('id', $shared_id)
                ->with(['location', 'provider', 'price', 'ips', 'labels'])->first();
        });
    }

    public function ips()
    {
        return $this->hasMany(IPs::class, 'service_id', 'id');
    }

    public function location()
    {
        return $this->hasOne(Locations::class, 'id', 'location_id');
    }

    public function provider()
    {
        return $this->hasOne(Providers::class, 'id', 'provider_id');
    }

    public function price()
    {
        return $this->hasOne(Pricing::class, 'service_id', 'id');
    }

    public function labels()
    {
        return $this->hasMany(LabelsAssigned::class, 'service_id', 'id');
    }

    public function note(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Note::class, 'service_id', 'id');
    }

}
