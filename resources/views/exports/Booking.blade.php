<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Event Name</th>
            <th>Org Name</th>
            <th>Attendee</th>
            <th>Number</th>
            <th>Ticket</th>
            <th>Qty</th>
            <th>Disc</th>
            <th>B Amt</th>
            <th>Total</th>
            <th>Status</th>
            {{-- <th>Disable</th> --}}
            <th>Purchase Date</th>
          <th>Gateway</th>
            <th>PaymentId</th>
         <th>Disable</th>
          <th>is_refunded</th>
            <th>refunded_at</th>

        </tr>
    </thead>
    <tbody>
        @foreach($Booking as $index => $bookings)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $bookings['event_name'] }}</td>
                <td>{{ $bookings['org_name'] }}</td>
                <td>{{ $bookings['attendee'] }}</td>
                <td>{{ $bookings['number'] }}</td>
                <td>{{ $bookings['ticket_name'] }}</td>
                <td>{{ $bookings['quantity'] }}</td> <!-- ✅ Correct Qty -->
                <td>{{ $bookings['discount'] }}</td>
                <td>{{ $bookings['base_amount'] }}</td>
                <td>{{ $bookings['amount'] }}</td>
                <td>{{ $bookings['status'] }}</td>
                {{-- <td>
                    <input type="checkbox" {{ $bookings['disabled'] ? 'checked' : '' }} disabled>
                </td> --}}
                <td>{{ \Carbon\Carbon::parse($bookings['created_at'])->format('d-m-Y | h:i:s A') }}</td>
               <td>{{ $bookings['gateway'] }}</td>
                <td>{{ $bookings['payment_id'] }}</td>
            
<td>
    {{ $bookings['deleted_at'] ? 'Disable' : 'Enable' }}
</td>
 <td>{{ $bookings['is_refunded'] }}</td>
                 <td>{{ \Carbon\Carbon::parse($bookings['refunded_at'])->format('d-m-Y | h:i:s A') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
