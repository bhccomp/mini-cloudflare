@props([
    'id' => null,
    'class' => '',
    'containerClass' => '',
])

<section @if($id) id="{{ $id }}" @endif class="{{ $class }}">
    <div class="mx-auto w-full max-w-7xl px-6 py-16 lg:py-24 {{ $containerClass }}">
        {{ $slot }}
    </div>
</section>
