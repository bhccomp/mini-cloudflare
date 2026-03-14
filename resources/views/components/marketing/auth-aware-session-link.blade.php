@props([
    'class' => '',
    'formClass' => '',
])

@auth
    <form method="POST" action="{{ route('logout') }}" class="{{ $formClass }}">
        @csrf
        <button type="submit" {{ $attributes->merge(['class' => $class]) }}>Logout</button>
    </form>
@else
    <a href="{{ url('/login') }}" {{ $attributes->merge(['class' => $class]) }}>Login</a>
@endauth
