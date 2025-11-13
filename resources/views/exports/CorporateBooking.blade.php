<!-- resources/views/exports/bookings.blade.php -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event</th>
            <th>Event Dates</th>
            <th>Corporate User</th>
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
        @foreach($CorporateBooking as $index => $CorporateBookings)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $CorporateBookings->ticket->event->name ?? 'N/A' }}</td>
                <td>
                    {{ $CorporateBookings->ticket->event->event_start_date ?? 'N/A' }}
                    to
                    {{ $CorporateBookings->ticket->event->event_end_date ?? 'N/A' }}
                </td>
                <td>{{ $CorporateBookings->user->name ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->ticket->event->organizer->name ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->ticket->name ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->name ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->number ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->quantity ?? 'N/A' }}</td>
                <td>{{ $CorporateBookings->discount ?? '0' }}</td>
                <td>{{ $CorporateBookings->amount ?? '0' }}</td>
                <td>{{ $CorporateBookings->status ?? 'N/A' }}</td>
                {{-- <td>Uncheck</td> --}}
                <td>{{ $CorporateBookings->created_at->format('d-m-Y | h:i:s A') }}</td>
                <td>
                    <input type="checkbox" {{ $CorporateBookings->is_disabled ? 'checked' : '' }}>
                </td>

            </tr>
        @endforeach
    </tbody>
</table>
