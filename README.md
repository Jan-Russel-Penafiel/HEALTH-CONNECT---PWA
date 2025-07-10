# HealthConnect - Barangay Health Center Management System

A Progressive Web Application (PWA) for managing health services at the Barangay Health Center of Brgy. Poblacion, President Quirino, Sultan Kudarat.

## Features

- 📱 Progressive Web App (PWA) with offline capabilities
- 🔐 Role-based authentication system
- 👥 Patient records management
- 📅 Appointment scheduling
- 💉 Immunization tracking
- 📊 Data visualization with Chart.js
- 📱 Mobile-responsive design
- 📨 SMS notification system

## Technical Stack

- Frontend: HTML5, CSS3, JavaScript
- Backend: PHP
- Database: MySQL
- PWA Features: Service Workers, Manifest
- Libraries: Chart.js, Font Awesome
- Design: Mobile-first responsive design

## Installation

1. Clone the repository to your XAMPP htdocs folder:
   ```bash
   git clone https://github.com/yourusername/healthconnect.git
   ```

2. Import the database:
   - Open phpMyAdmin
   - Create a new database named 'healthconnect'
   - Import the `healthconnect.sql` file

3. Configure the database connection:
   - Navigate to `includes/config/database.php`
   - Update the database credentials if needed

4. Access the application:
   - Open your browser and navigate to `http://localhost/connect`
   - For PWA installation, use HTTPS in production

## Project Structure

```
connect/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── includes/
│   └── config/
├── pages/
├── index.php
├── manifest.json
├── service-worker.js
└── healthconnect.sql
```

## Security Features

- Password hashing
- Session management
- SQL injection prevention
- Input validation
- Secure cookie handling

## PWA Features

- Offline functionality
- Install prompt
- App-like experience
- Push notifications (where supported)
- Responsive design

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please contact the development team or create an issue in the repository. 