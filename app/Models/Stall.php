<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable(['event_id', 'vendor_user_id', 'name', 'description', 'logo_url', 'location', 'products', 'offers'])]
class Stall extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'view_count' => 'integer',
            'link_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Stall $stall) {
            $stall->public_code ??= Str::lower(Str::random(10));
        });
    }

    /** Signed private link for the vendor: scan count, leads, and the lead-capture scanner. */
    public function vendorUrl(): string
    {
        return URL::signedRoute('vendor.show', [$this, 'v' => $this->link_version]);
    }

    /** Invalidate every previously shared vendor link. */
    public function regenerateVendorLink(): void
    {
        $this->increment('link_version');
    }

    public function getRouteKeyName(): string
    {
        return 'public_code';
    }

    public function logoUrl(): ?string
    {
        return $this->logo_url ? Storage::disk('public')->url($this->logo_url) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
