<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">New brand</p>
                <h1>Create a brand</h1>
            </div>
        </div>
    </x-slot>

    @include('brands._form', ['action' => route('brands.store'), 'method' => 'POST', 'brand' => null])
</x-app-layout>
