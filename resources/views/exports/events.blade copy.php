<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Category</th>
            <th>Organizer</th>
            <th>Event Dates</th>
            <th>Ticket Type</th>
            <th>Status</th>
            <th>Created At</th>
        </tr>
    </thead>
    <tbody>
        @foreach($events as $event)
            <tr>
                <td>{{ $event->name }} </td>
                <td>{{ $event->Category->title }}</td>
                <td>{{ $event->organizer ? $event->organizer->name : 'No Organizer' }}</td>
                {{-- <td>{{ $event->organizer->name }}</td> --}}
                <td>{{ $event->date_range }}</td>
                <td>{{ $event->event_type }}</td>
                <td>{{ $event->status }}</td>
                <td>{{ $event->created_at->format('d-m-Y | h:i:s A') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
