<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Edit brand</p>
                <h1>{{ $brand->name }}</h1>
            </div>
        </div>
    </x-slot>

    @include('brands._form', ['action' => route('brands.update', $brand), 'method' => 'PUT', 'brand' => $brand])
</x-app-layout>
