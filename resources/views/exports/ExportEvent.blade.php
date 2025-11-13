<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event</th>
            <th>Organizer</th>
            <th>Available Tickets</th>
            <th>Online</th>
            <th>Agent</th>
            <th>POS</th>
            <th>Total Tickets</th>
            <th>Check-ins</th>
            <th>Online Sale</th>
            <th>Agent Sale</th>
            <th>Agent + POS</th>
            <th>Discount</th>
            <th>Convenience Fees</th>
        </tr>
    </thead>
    <tbody>
        @foreach($eventReport as $index => $booking)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $booking->name ?? 'N/A' }}</td>
                <td>{{ $booking->user->name ?? 'N/A' }}</td>
                <td>{{ $booking->available_tickets ?? 'N/A' }}</td>
                <td>{{ $booking->online ?? '0' }}</td>
                <td>{{ $booking->agent ?? '0' }}</td>
                <td>{{ $booking->pos ?? '0' }}</td>
                <td>{{ $booking->total_tickets ?? '0' }}</td>
                <td>{{ $booking->checkins ?? '0' }}</td>
                <td>{{ $booking->online_sale ?? '₹ 0.00' }}</td>
                <td>{{ $booking->agent_sale ?? '₹ 0.00' }}</td>
                <td>{{ $booking->agent_pos ?? '₹ 0.00' }}</td>
                <td>{{ $booking->discount ?? '₹ 0.00' }}</td>
                <td>{{ $booking->convenience_fees ?? '₹ 0.00' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
