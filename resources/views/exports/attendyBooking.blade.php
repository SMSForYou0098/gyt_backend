{{-- <table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="6">Event: {{ $eventName }}</th>
        </tr>
        <tr>
            <th>#</th>
            <th>Event</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Photo</th>
            <th>Token</th>
        </tr>
    </thead>
    <tbody>
        @forelse($attendees as $index => $reports)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $eventName }}</td>
                <td>{{ $reports->Name ?? 'N/A' }}</td>
                <td>{{ $reports->Mo ?? 'N/A' }}</td>
                <td>{{ $reports->Email ?? 'N/A' }}</td>
                <td>{{ $reports->Photo ?? 'N/A' }}</td>
                <td>{{ $reports->token ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No attendees found</td>
            </tr>
        @endforelse
    </tbody>
</table> --}}
<table class="table table-bordered">
    <thead>
        <tr>
            <th colspan="7">Event: {{ $eventName }}</th>
        </tr>
        <tr>
            <th>#</th>
            <th>Type</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Photo</th>
            <th>Token</th>
            <th>Ticket Name</th>
        </tr>
    </thead>
    <tbody>
        @php $index = 1; @endphp

        {{-- Attendees --}}
        @foreach ($attendees as $reports)
            <tr>
                <td>{{ $index++ }}</td>
                <td>Attendee</td>
                <td>{{ $reports->Name ?? 'N/A' }}</td>
                <td>{{ $reports->Mo ?? 'N/A' }}</td>
                <td>{{ $reports->Email ?? 'N/A' }}</td>
                <td>{{ $reports->Photo ?? 'N/A' }}</td>
                <td>{{ $reports->token ?? 'N/A' }}</td>
                <td>
                    {{ $reports->booking?->ticket?->name ?? ($reports->agentBooking?->ticket?->name ?? 'N/A') }}
                </td>

            </tr>
        @endforeach

        {{-- Corporate Users --}}
        @foreach ($corporateUsers as $corp)
            <tr>
                <td>{{ $index++ }}</td>
                <td>Corporate</td>
                <td>{{ $corp->Name ?? 'N/A' }}</td>
                <td>{{ $corp->Mo ?? 'N/A' }}</td>
                <td>{{ $corp->Email ?? 'N/A' }}</td>
                <td>{{ $corp->Photo ?? 'N/A' }}</td>
                <td>{{ $corp->token ?? 'N/A' }}</td>
                <td>{{ $corp->booking?->ticket?->name ?? 'N/A' }}</td>

            </tr>
        @endforeach

        @if ($index === 1)
            <tr>
                <td colspan="7">No data found</td>
            </tr>
        @endif
    </tbody>
</table>
