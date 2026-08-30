@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.deletion_requests.title') }}</h1>
        <p class="muted">{{ __('admin.deletion_requests.hint') }}</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.deletion_requests.created') }}</th>
                    <th>{{ __('admin.deletion_requests.user') }}</th>
                    <th>{{ __('admin.deletion_requests.status') }}</th>
                    <th>{{ __('admin.deletion_requests.note') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            {{ $item->user?->name }}<br>
                            <span class="muted">{{ $item->user?->email }}</span>
                        </td>
                        <td>{{ __('admin.deletion_requests.status_'.$item->status) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $item->note, 80) }}</td>
                        <td>
                            @if ($item->isPending())
                                <form
                                    method="POST"
                                    action="{{ route('admin.deletion-requests.process', $item) }}"
                                    style="display:inline"
                                    onsubmit="return confirm(@json(__('admin.deletion_requests.process_confirm')))"
                                >
                                    @csrf
                                    <button type="submit">{{ __('admin.deletion_requests.process') }}</button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('admin.deletion-requests.reject', $item) }}"
                                    style="display:inline"
                                    onsubmit="return confirm(@json(__('admin.deletion_requests.reject_confirm')))"
                                >
                                    @csrf
                                    <button type="submit">{{ __('admin.deletion_requests.reject') }}</button>
                                </form>
                            @elseif ($item->processed_at)
                                <span class="muted">{{ $item->processed_at->format('Y-m-d H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">{{ __('admin.deletion_requests.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem">
            {{ $requests->links() }}
        </div>
    </main>
@endsection
