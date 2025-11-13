<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event Name</th>
            <th>Sponsor Name</th>
            <th>User Name</th>
            <th>Number</th>
            <th>Ticket</th>
            <th>Qty</th>
            <th>B Amt</th>
            <th>Disc</th>
            <th>Total</th>
            <th>Status</th>
            {{-- <th>Disable</th> --}}
           
          <th>Token</th>
        </tr>
    </thead>
    <tbody>
            @foreach($bookings as $index => $booking)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $booking->event_name ?? 'N/A' }}</td>
                    <td>{{ $booking->sponsorUser->name ?? 'N/A' }}</td>
                    <td>{{ $booking->user->name ?? 'No User' }}</td>
                    <td>{{ $booking->number ?? '' }}</td>
                    <td>{{ $booking->ticket->name ?? '' }}</td>
                    <td>{{ $booking->quantity ?? 0 }}</td>
                    <td>{{ $booking->base_amount ?? 0 }}</td>
                    <td>{{ $booking->discount ?? 0 }}</td>
                    <td>{{ $booking->amount ?? 0 }}</td>
                    <td>{{ $booking->status ?? ''}}</td>                   
                  
                  <td>{{ $booking->token  ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
</table>
