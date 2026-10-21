@props(['user', 'large' => false])
<img src="{{ route('employees.avatar', $user) }}" data-avatar="{{ route('employees.avatar', $user) }}"
    alt="{{ $user->name }} profile picture" width="{{ $large ? 88 : 40 }}" height="{{ $large ? 88 : 40 }}"
    {{ $attributes->class(['account-avatar', 'account-avatar-lg' => $large]) }} loading="lazy" decoding="async">
