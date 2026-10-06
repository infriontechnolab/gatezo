@extends('layouts.public', ['title' => 'Privacy policy · Gatezo', 'noindex' => false, 'wide' => true])
@section('content')
@component('legal._page', ['heading' => 'Privacy policy', 'updated' => '5 October 2026'])

<p>Gatezo is a tool that event organizers use to register people, check them in at the gate with a QR pass, run volunteers, stalls, goodies, lucky draws and feedback, and see who came. This page explains what personal data Gatezo holds, why, who sees it, and what you can do about it.</p>

<h2>Who is responsible for your data</h2>
<ul>
    <li><b>If you organize events on Gatezo</b> (or were invited to an organizer's team), Gatezo is responsible for your account data.</li>
    <li><b>If you registered for an event</b>, the event's organizer decides what to collect and what to do with it. Gatezo stores and processes it for them. In the words of the law, the organizer is the <i>data fiduciary</i> and Gatezo is their <i>data processor</i>. Requests about an event's data are best sent to its organizer; you can also write to us and we will help.</li>
</ul>

<h2>What we collect</h2>

<h3>Organizers and their team</h3>
<ul>
    <li>Name, email address and WhatsApp number, given when you sign up or are invited.</li>
    <li>Your password, stored only as a one-way hash. Nobody at Gatezo can read it.</li>
    <li>The events you create and their settings.</li>
    <li>Plan requests and payment records: plan, period, amount and payment reference. Payments are made by UPI or bank transfer outside Gatezo; we never see card or bank login details.</li>
</ul>

<h3>People who register for an event</h3>
<ul>
    <li>Name and phone number. Email too, if the organizer asks for it.</li>
    <li>Anything else the organizer imports from their own list (for example a ticket type or a column from a Google Form).</li>
    <li>Your pass, and when and at which gate it was scanned in or out.</li>
    <li>Whether you collected the event's goodies or gift, and any lucky draw prize you won.</li>
    <li>Feedback you leave: a star rating and an optional comment. It is anonymous unless you send it from your pass page.</li>
    <li>Your answers to two optional questions: whether stalls at the event may contact you, and whether the organizer may send you WhatsApp updates about their upcoming events, with the time you answered.</li>
</ul>

<h3>Volunteers</h3>
<ul>
    <li>Your name, your shifts and posts, and the scans you made.</li>
    <li>When you join the scanner: your IP address, your browser's description of itself, and a random code stored in a cookie on your phone. These stop people guessing the event code and show the organizer which phones joined.</li>
</ul>

<h3>Everyone who visits the website</h3>
<ul>
    <li>Cookies that keep you signed in and protect forms from forgery, and the volunteer device cookie above. We use no advertising or analytics cookies and no tracking pixels.</li>
    <li>The home page loads its fonts from Google Fonts, so your browser contacts Google, which sees your IP address.</li>
    <li>Our server keeps short-lived technical logs (such as errors) to keep the service running.</li>
</ul>

<h2>How it is used</h2>
<ul>
    <li><b>To run the event:</b> issue your pass, check you in, hand out goodies, run the lucky draw and produce the organizer's report.</li>
    <li><b>The organizer's visitor list:</b> Gatezo shows each organizer one list of the people who registered for any of their events, matched by phone number, with how many of their events each person came to.</li>
    <li><b>WhatsApp updates:</b> only people who said yes appear in the organizer's export. The organizer sends any message from their own WhatsApp; Gatezo does not send WhatsApp messages. Reply STOP to the organizer, or ask them, and they mark you as opted out.</li>
    <li><b>Stall leads:</b> only if you turned on "Allow stalls to contact me" (on your pass page, off by default) can a stall you visit save your name, phone and email.</li>
    <li><b>Emails from Gatezo:</b> account emails such as setting or resetting a password and team invitations, and replies about plans you asked for.</li>
</ul>
<p>We do not sell personal data, show ads, or use your data for anything other than running Gatezo for the organizer.</p>

<h2>Who else sees it</h2>
<ul>
    <li><b>The organizer and the team members they invite.</b> They can see and export their events' attendee lists.</li>
    <li><b>Volunteers</b> see attendees' names, ticket type and the last four digits of their phone number while scanning. So that scanning works without signal, the scanner phone stores this list in its browser until the browser's site data is cleared.</li>
    <li><b>Stall owners</b>, only for people who allowed it, as above.</li>
    <li><b>Service providers</b> that run Gatezo for us: our hosting provider (servers in India), our email delivery provider (which may process email addresses outside India), and backup storage. They may only use the data to provide their service to us.</li>
    <li><b>Authorities</b>, when the law requires us to share it.</li>
</ul>

<h2>How long it is kept</h2>
<ul>
    <li><b>Event data</b> stays until the organizer removes it. Organizers can remove any person from their list at any time; to delete a whole event, or their account, they write to us. Removing someone from every one of an organizer's events also removes them from that organizer's visitor list.</li>
    <li><b>Account data</b> stays while the account is open, and is deleted when the organizer asks us to close it.</li>
    <li><b>Backups</b> hold a copy of the database for up to 14 days after something is deleted.</li>
    <li>Payment records may be kept longer where tax law requires it.</li>
</ul>

<h2>Your rights</h2>
<p>Under the Digital Personal Data Protection Act, 2023 you can:</p>
<ul>
    <li>ask what data is held about you and who it was shared with,</li>
    <li>have it corrected or completed,</li>
    <li>have it erased, unless the law requires us to keep it,</li>
    <li>withdraw consent you gave (for example to WhatsApp updates or to stalls contacting you) as easily as you gave it,</li>
    <li>nominate someone to act for you, and</li>
    <li>complain to us, and then to the Data Protection Board of India.</li>
</ul>
<p>For an event's data, ask its organizer first, or write to us and we will pass the request on and help them act on it. On your pass page you can turn "Allow stalls to contact me" off at any time.</p>

<h2>Children</h2>
<p>Gatezo is not meant for children to sign up or register themselves. If a child is coming to an event, a parent or guardian should register them.</p>

<h2>Security</h2>
<p>Gatezo is served over HTTPS, passwords are hashed, passes are signed so they cannot be forged, and an organizer's data is visible only to their own team. No system is perfectly secure; if a breach affects your data we will tell those affected and the authorities as the law requires.</p>

<h2>Changes</h2>
<p>If this policy changes, we will update the date at the top. If the change matters to how your data is used, organizers will also hear about it by email.</p>

@endcomponent
@endsection
