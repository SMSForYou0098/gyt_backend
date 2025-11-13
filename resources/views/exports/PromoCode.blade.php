<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Code</th>
            <th>Description</th>
            <th>Discount Type</th>
            <th>Discount Value</th>
            <th>Minimum Spend</th>
            <th>Usage Limit</th>
            <th>Usage Per User</th>
            <th>Status</th>
            <th>Created At</th>
        </tr>
    </thead>
    <tbody>
        @foreach($PromoCode as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->code }}</td>
            <td>{{ $user->description }}</td>
            <td>{{ $user->discount_type }}</td>
            <td>{{ $user->discount_value }}</td>
            <td>{{ $user->minimum_spend }}</td>
            <td>{{ $user->usage_limit }}</td>
            <td>{{ $user->usage_per_user }}</td>
            <td>{{ $user->status }}</td>
            <td>{{ $user->created_at }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
