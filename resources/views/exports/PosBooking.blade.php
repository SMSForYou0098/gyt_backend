<!-- resources/views/exports/bookings.blade.php -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event</th>
            <th>Event Dates</th>
            <th>POS User</th>
            <th>Organizer</th>
            <th>Ticket</th>
            <th>Name</th>
            <th>Number</th>
            <th>Quantity</th>
            <th>Discount</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Purchase Date</th>
            {{-- <th>Disable</th> --}}

        </tr>
    </thead>
    <tbody>
        @foreach($PosBooking as $index => $PosBookings)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $PosBookings->ticket->event->name ?? 'N/A' }}</td>
                <td>
                    {{ $PosBookings->ticket->event->event_start_date ?? 'N/A' }}
                    to
                    {{ $PosBookings->ticket->event->event_end_date ?? 'N/A' }}
                </td>
                <td>{{ $PosBookings->user->name ?? 'N/A' }}</td>
                <td>{{ $PosBookings->ticket->event->organizer->name ?? 'N/A' }}</td>
                <td>{{ $PosBookings->ticket->name ?? 'N/A' }}</td>
                <td>{{ $PosBookings->name ?? 'N/A' }}</td>
                <td>{{ $PosBookings->number ?? 'N/A' }}</td>
                <td>{{ $PosBookings->quantity ?? 'N/A' }}</td>
                <td>{{ $PosBookings->discount ?? '0' }}</td>
                <td>{{ $PosBookings->amount ?? '0' }}</td>
                <td>{{ $PosBookings->status ?? 'N/A' }}</td>
                {{-- <td>Uncheck</td> --}}
                <td>{{ $PosBookings->created_at->format('d-m-Y | h:i:s A') }}</td>
                <td>
                    <input type="checkbox" {{ $PosBookings->is_disabled ? 'checked' : '' }}>
                </td>

            </tr>
        @endforeach
    </tbody>
</table>
