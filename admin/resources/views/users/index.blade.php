@php
    // Same table classes as the events list. Copied rather than extracted: two
    // tables do not need a shared component yet.
    $th = 'px-4 py-3 text-start text-xs font-bold uppercase tracking-wide text-muted whitespace-nowrap';
    $td = 'px-4 py-3 align-middle text-sm whitespace-nowrap';

    // Manager is the role that can change things, so it is the one that gets a
    // colour. Anything unmapped falls back to slate inside <x-badge>.
    $roleTones = [
        'manager' => 'blue',
        'worker' => 'slate',
    ];
@endphp

<x-layouts.app title="Users">
    <x-slot:actions>
        <x-button variant="primary" :href="route('users.create')">Add user</x-button>
    </x-slot:actions>

    <x-card>
        @if ($users->isEmpty())
            {{-- Only reachable if someone deletes every row straight in MySQL:
                 you have to be logged in to see this screen at all. --}}
            <x-empty-state title="No panel accounts"
                           body="Nobody can sign in to the panel. Re-run the seeder, or add the first account here.">
                <x-slot:action>
                    <x-button variant="primary" :href="route('users.create')">Add user</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-hairline">
                            <th scope="col" class="{{ $th }}">Name</th>
                            <th scope="col" class="{{ $th }}">Email</th>
                            <th scope="col" class="{{ $th }}">Role</th>
                            <th scope="col" class="{{ $th }}">Added</th>
                            <th scope="col" class="{{ $th }}"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php $isSelf = $user->is(auth()->user()); @endphp

                            <tr class="border-b border-hairline last:border-0 hover:bg-canvas">
                                <td class="{{ $td }}">
                                    <span class="font-semibold">{{ $user->name }}</span>

                                    {{-- Marks the row whose Delete button is missing, so the
                                         gap reads as deliberate rather than broken. --}}
                                    @if ($isSelf)
                                        <span class="ms-1.5 text-xs font-bold text-muted">(you)</span>
                                    @endif
                                </td>

                                <td class="{{ $td }} text-muted">{{ $user->email }}</td>

                                <td class="{{ $td }}">
                                    <x-badge :tone="$roleTones[$user->role] ?? 'slate'">{{ $user->role }}</x-badge>
                                </td>

                                <td class="{{ $td }} text-muted">{{ $user->created_at->format('d M Y') }}</td>

                                <td class="{{ $td }} text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button :href="route('users.edit', $user)">Edit</x-button>

                                        {{-- Hidden for your own row. The controller refuses it
                                             too — this only keeps a dead button off the screen. --}}
                                        @unless ($isSelf)
                                            {{-- A form, not a link: DELETE cannot be a GET, and
                                                 @method spoofs the verb browsers will not send. --}}
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                  onsubmit="return confirm(@js("Delete {$user->name}? This cannot be undone."))">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" variant="danger">Delete</x-button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="flex flex-wrap items-center gap-3 border-t border-hairline px-4 py-3">
                    <p class="text-[13px] text-muted">
                        Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}
                    </p>

                    <div class="ms-auto flex items-center gap-2.5">
                        @if ($users->onFirstPage())
                            <span class="rounded-xl border border-hairline px-4 py-2.5 text-sm font-bold text-hairline">Previous</span>
                        @else
                            <x-button :href="$users->previousPageUrl()" rel="prev">Previous</x-button>
                        @endif

                        @if ($users->hasMorePages())
                            <x-button variant="primary" :href="$users->nextPageUrl()" rel="next">Next</x-button>
                        @else
                            <span class="rounded-xl border border-hairline px-4 py-2.5 text-sm font-bold text-hairline">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
