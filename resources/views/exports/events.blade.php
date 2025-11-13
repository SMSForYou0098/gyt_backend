<table>
    <thead>
        <tr>
            <th>Sr No</th>
            <th>Name</th>
            <th>Category</th>
            <th>Organizer</th>
            <th>Event Date</th>
            <th>Event Type</th>
            <th>Status</th>
            <th>Organisation</th>
        </tr>
    </thead>
    <tbody>
        @foreach($events as $event)
            <tr>
                <td>{{ $event['sr_no'] }}</td>
                <td>{{ $event['name'] }}</td>
                <td>{{ $event['category'] }}</td>
                <td>{{ $event['organizer'] }}</td>
                <td>{{ $event['event_date'] }}</td>
                <td>{{ $event['event_type'] }}</td>
                <td>{{ $event['status'] }}</td>
                <td>{{ $event['organisation'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>