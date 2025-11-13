<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>New Contact Ticket</title>
</head>
<body style="margin: 0; padding: 0; background-color: #3F249F; font-family: Arial, sans-serif;">
  <table align="center" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; margin: 40px auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden;">
    <tr>
      <td style="background-color: #3F249F; color: #ffffff; padding: 20px 30px; font-size: 24px; font-weight: bold;">
        📩 <span style="color: hsl(348, 24%, 96%);">New Contact Ticket</span>
      </td>
    </tr>
    <tr>
      <td style="padding: 30px;">
        <p><strong style="color:#3F249F;">Name:</strong> {{ $contactData->name ?? '-' }}</p>
        <p><strong style="color:#3F249F;">Email:</strong> {{ $contactData->email ?? '-' }}</p>
        <p><strong style="color:#3F249F;">Phone:</strong> {{ $contactData->number ?? '-' }}</p>
        <p><strong style="color:#3F249F;">Subject:</strong> {{ $contactData->subject ?? 'Contact Form Submission' }}</p>
        <p><strong style="color:#3F249F;">Address:</strong> {{ $contactData->address ?? '-' }}</p>
        <p><strong style="color:#3F249F;">Message:</strong> {{ $contactData->message ?? '-' }}</p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">

        @if(!empty($contactData->image))
          @php
              $imagePath = is_string($contactData->image) && !Str::startsWith($contactData->image, 'http')
                          ? url('uploads/contactUs/' . basename($contactData->image))
                          : $contactData->image;
          @endphp
          <p><strong style="color:#3F249F;">Screenshot:</strong></p>
          <a href="{{ $imagePath }}" style="display:inline-block; background-color: #C6284A; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold;" target="_blank">
            View Attachment
          </a>
        @endif

        <p style="margin-top: 30px; font-size: 13px; color: #888;">
          This message was sent via your contact form.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
