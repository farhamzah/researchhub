<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpertValidator extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'created_by',
        'name',
        'email',
        'phone',
        'institution',
        'position',
        'expertise_areas',
        'notes',
        'is_active',
        'is_global',
    ];

    protected function casts(): array
    {
        return [
            'expertise_areas' => 'array',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExpertValidatorProject::class);
    }

    public function researchProjects(): BelongsToMany
    {
        return $this->belongsToMany(ResearchProject::class, 'expert_validator_project', 'expert_validator_id', 'research_project_id')
            ->withPivot([
                'id',
                'role',
                'expertise_scope',
                'status',
                'invited_at',
                'accepted_at',
                'notes',
                'created_by',
            ])
            ->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('created_by', $user->getKey())
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('is_global', true)
                        ->where('is_active', true);
                });
        });
    }
}
