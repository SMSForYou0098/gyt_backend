<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Number</th>
            <th>Event Name</th>
            <th>Ticket Type</th>
            <th>Total Bookings</th>
            <th>Disable</th>
            <th>Generate Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($complimentaryBookings as $index => $booking)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $booking->name ?? 'N/A' }}</td>
                <td>{{ $booking->number ?? 'N/A' }}</td>
                <td>{{ $booking->ticket->event->name ?? 'N/A' }}</td>
                <td>{{ $booking->ticket->name ?? 'N/A' }}</td>
                <td>{{ $booking->totalBookingsCount() ?? 'N/A' }}</td>
                <td>
                    <!-- Here, you might have a checkbox or a value based on disable status -->
                    <input type="checkbox" {{ $booking->is_disabled ? 'checked' : '' }} disabled>
                </td>
                <td>{{ $booking->created_at->format('d-m-Y | h:i:s A') }}</td>

            </tr>
        @endforeach
    </tbody>
</table>
