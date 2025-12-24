<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Refund Initiated</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

<p>Dear {{ $booking->name }},</p>

<p>
    Thank you for purchasing your ticket with us.
    Your refund for <strong>{{ $booking->ticket->event->name }}</strong> has been initiated.
</p>

<p>
    We sincerely apologize for the inconvenience caused.
    Your refund for the event has been successfully initiated.
</p>

<p>
    If you have any questions, please call us at <strong>8000308888</strong>.<br>
    Email us: <a href="mailto:refund@getyourticket.in">refund@getyourticket.in</a>
</p>

<p>
    For future events and promo codes, follow us on our social media:
</p>

<ul>
    <li>Instagram: <a href="https://insta.gyt.co.in">insta.gyt.co.in</a></li>
    <li>Facebook: <a href="https://fb.gyt.co.in">fb.gyt.co.in</a></li>
    <li>WhatsApp channel: <a href="https://wa.gyt.co.in">wa.gyt.co.in</a></li>
    <li>YouTube: <a href="https://yt.gyt.co.in">yt.gyt.co.in</a></li>
</ul>

<p>
    Thank you for your understanding and support.
</p>

<p>
    Regards,<br>
    <strong>Team Get Your Ticket</strong>
</p>

</body>
</html>
