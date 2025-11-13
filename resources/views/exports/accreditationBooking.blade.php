<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event</th>
            <th>Agent</th>
            <th>Access Area</th>
            <th>Image</th>
            <th>Document</th>
            <th>Designation</th>
            <th>Company Name</th>
            <th>User</th>
            <th>Number</th>
            <th>Ticket</th>
            <th>Status</th>
            <th>Booking Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($bookings as $index => $booking)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $booking->event_name ?? 'N/A' }}</td>
                <td>{{ $booking->user->name ?? 'N/A' }}</td>
                <td>
                    @if(is_array($booking->access_area))
                        {{ implode(', ', $booking->access_area) }}
                    @else
                        {{ $booking->access_area ?? 'N/A' }}
                    @endif
                </td>
                
                
                {{-- Image --}}
                <td>
                    {{ $booking->user->photo ?? 'N/A' }}
                   
                </td>
                
                <td>
                    {{ $booking->user->doc ?? 'N/A' }}
                </td>
                

                <td>{{ $booking->user->designation ?? 'N/A' }}</td>
                <td>{{ $booking->user->company_name ?? 'N/A' }}</td>
                <td>{{ $booking->user->name ?? 'N/A' }}</td>
                <td>{{ $booking->user->number ?? 'N/A' }}</td>
                <td>{{ $booking->ticket_type ?? 'N/A' }}</td>

                {{-- Status --}}
                <td>
                    <span class="badge bg-{{ $booking->status == 'Checked' ? 'success' : 'warning' }}">
                        {{ $booking->status ?? 'Uncheck' }}
                    </span>
                </td>

                {{-- Booking Date --}}
                <td>{{ \Carbon\Carbon::parse($booking->created_at)->format('d-m-Y | h:i:s A') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
