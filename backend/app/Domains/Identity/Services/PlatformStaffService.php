<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PlatformRole;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformStaffService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()
            ->where('is_platform_admin', true)
            ->orderByRaw("CASE platform_role WHEN 'owner' THEN 1 WHEN 'manager' THEN 2 WHEN 'support' THEN 3 ELSE 9 END")
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, password: string, platform_role: string}  $data
     */
    public function create(User $actor, array $data): User
    {
        $this->assertActorIsOwner($actor);

        $role = $data['platform_role'];
        if (! PlatformRole::isValid($role) || $role === PlatformRole::OWNER) {
            throw ValidationException::withMessages([
                'platform_role' => ['New staff may only be Manager or Support. Promote to Owner separately if needed.'],
            ]);
        }

        if (User::query()->where('email', strtolower(trim($data['email'])))->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email already exists.'],
            ]);
        }

        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'is_platform_admin' => true,
            'platform_role' => $role,
            'workspace_status' => User::WORKSPACE_COMPLETE,
        ]);

        $this->audit->log('platform.staff.created', $user, null, [
            'email' => $user->email,
            'platform_role' => $user->platform_role,
        ], $actor);

        return $user;
    }

    /**
     * @param  array{name?: string, platform_role?: string}  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $this->assertActorIsOwner($actor);
        $this->assertTargetIsPlatformStaff($target);

        $old = [
            'name' => $target->name,
            'platform_role' => $target->platform_role,
        ];

        if (array_key_exists('name', $data) && filled($data['name'])) {
            $target->name = trim((string) $data['name']);
        }

        if (array_key_exists('platform_role', $data) && filled($data['platform_role'])) {
            $role = (string) $data['platform_role'];
            if (! PlatformRole::isValid($role)) {
                throw ValidationException::withMessages([
                    'platform_role' => ['Invalid platform role.'],
                ]);
            }

            if ($target->id === $actor->id && $role !== PlatformRole::OWNER) {
                throw ValidationException::withMessages([
                    'platform_role' => ['You cannot demote your own owner role.'],
                ]);
            }

            if (
                $role !== PlatformRole::OWNER
                && $this->effectiveRole($target) === PlatformRole::OWNER
                && $this->ownerCount() <= 1
            ) {
                throw ValidationException::withMessages([
                    'platform_role' => ['At least one platform owner must remain.'],
                ]);
            }

            $target->platform_role = $role;
            $target->is_platform_admin = true;
        }

        $target->save();

        $this->audit->log('platform.staff.updated', $target, $old, [
            'name' => $target->name,
            'platform_role' => $target->platform_role,
        ], $actor);

        return $target->fresh();
    }

    public function setPassword(User $actor, User $target, string $password): User
    {
        $this->assertActorIsOwner($actor);
        $this->assertTargetIsPlatformStaff($target);

        $target->forceFill(['password' => Hash::make($password)])->save();
        $target->tokens()->delete();

        $this->audit->log('platform.staff.password_reset', $target, null, [
            'user_id' => $target->id,
        ], $actor);

        return $target;
    }

    public function revoke(User $actor, User $target): User
    {
        $this->assertActorIsOwner($actor);
        $this->assertTargetIsPlatformStaff($target);

        if ($target->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot revoke your own platform access.'],
            ]);
        }

        if ($this->effectiveRole($target) === PlatformRole::OWNER && $this->ownerCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['At least one platform owner must remain.'],
            ]);
        }

        $old = [
            'is_platform_admin' => $target->is_platform_admin,
            'platform_role' => $target->platform_role,
        ];

        $target->forceFill([
            'is_platform_admin' => false,
            'platform_role' => null,
        ])->save();
        $target->tokens()->delete();

        $this->audit->log('platform.staff.revoked', $target, $old, [
            'is_platform_admin' => false,
        ], $actor);

        return $target->fresh();
    }

    /**
     * @param  array{name?: string, email?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        if (! $user->is_platform_admin) {
            throw ValidationException::withMessages([
                'user' => ['Platform access required.'],
            ]);
        }

        $old = ['name' => $user->name, 'email' => $user->email];

        if (array_key_exists('name', $data) && filled($data['name'])) {
            $user->name = trim((string) $data['name']);
        }

        if (array_key_exists('email', $data) && filled($data['email'])) {
            $email = strtolower(trim((string) $data['email']));
            $taken = User::query()
                ->where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => ['That email is already in use.'],
                ]);
            }
            $user->email = $email;
        }

        $user->save();

        $this->audit->log('platform.profile.updated', $user, $old, [
            'name' => $user->name,
            'email' => $user->email,
        ], $user);

        return $user->fresh();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! $user->is_platform_admin) {
            throw ValidationException::withMessages([
                'user' => ['Platform access required.'],
            ]);
        }

        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        $this->audit->log('platform.profile.password_changed', $user, null, [
            'user_id' => $user->id,
        ], $user);
    }

    /**
     * @return array{id: int|string, name: string, email: string, is_platform_admin: bool, platform_role: string|null, platform_role_label: string|null}
     */
    public function serialize(User $user): array
    {
        $role = $this->effectiveRole($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_platform_admin' => (bool) $user->is_platform_admin,
            'platform_role' => $role,
            'platform_role_label' => $role ? PlatformRole::label($role) : null,
            'created_at' => optional($user->created_at)?->toIso8601String(),
        ];
    }

    private function assertActorIsOwner(User $actor): void
    {
        if ($this->effectiveRole($actor) !== PlatformRole::OWNER) {
            throw ValidationException::withMessages([
                'user' => ['Only platform owners can manage staff.'],
            ]);
        }
    }

    private function assertTargetIsPlatformStaff(User $target): void
    {
        if (! $target->is_platform_admin) {
            throw ValidationException::withMessages([
                'user' => ['User is not a platform staff member.'],
            ]);
        }
    }

    private function effectiveRole(User $user): ?string
    {
        return PlatformRole::effective((bool) $user->is_platform_admin, $user->platform_role);
    }

    private function ownerCount(): int
    {
        return User::query()
            ->where('is_platform_admin', true)
            ->where(function ($q) {
                $q->where('platform_role', PlatformRole::OWNER)
                    ->orWhereNull('platform_role');
            })
            ->count();
    }
}
