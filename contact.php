<?php include 'header.php'; ?>
<div class="container content-page">
    <h1>Contact Us</h1>
    <p>If you have any questions, concerns, or feedback, please don't hesitate to reach out to us using the contact form below or via the provided contact information.</p>
    <div class="contact-info">
        <p><strong>Email:</strong> info@eventcraftai.com</p>
        <p><strong>Phone:</strong> +1 (123) 456-7890</p>
        <p><strong>Address:</strong> 123 Event Lane, City, State, Country</p>
    </div>
    <form class="contact-form">
        <div class="form-group">
            <label for="name">Your Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Your Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" required>
        </div>
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send Message</button>
    </form>
</div>
<?php include 'footer.php'; ?>