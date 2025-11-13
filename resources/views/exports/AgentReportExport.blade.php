<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Total Bookings</th>
            <th>Today Bookings</th>
            <th>Today Collection</th>
            <th>UPI Bookings</th>
            <th>Cash Bookings</th>
            <th>Net Banking Bookings</th>
            <th>UPI Amount</th>
            <th>Cash Amount</th>
            <th>Net Banking Amount</th>
            <th>Total Discount</th>
            <th>Total Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report as $index => $reports)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $reports['agent_name'] ?? 'N/A' }}</td>
                <td>{{ $reports['booking_count'] ?? 0 }}</td>
                <td>{{ $reports['today_booking_count'] ?? 0 }}</td>
                <td>₹{{ number_format($reports['today_total_amount'] ?? 0, 2) }}</td>
                <td>{{ $reports['total_UPI_bookings'] ?? 0 }}</td>
                <td>{{ $reports['total_Cash_bookings'] ?? 0 }}</td>
                <td>{{ $reports['total_Net_Banking_bookings'] ?? 0 }}</td>
                <td>₹{{ number_format($reports['total_UPI_amount'] ?? 0, 2) }}</td>
                <td>₹{{ number_format($reports['total_Cash_amount'] ?? 0, 2) }}</td>
                <td>₹{{ number_format($reports['total_Net_Banking_amount'] ?? 0, 2) }}</td>
                <td>₹{{ number_format($reports['total_discount'] ?? 0, 2) }}</td>
                <td>₹{{ number_format($reports['total_amount'] ?? 0, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="13" class="text-center">No data available</td>
            </tr>
        @endforelse
    </tbody>
</table>
