<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-info">
                <p>&copy; <?php echo date('Y'); ?> HealthConnect - Barangay Health Center Management System</p>
                <p>Brgy. Poblacion, President Quirino, Sultan Kudarat</p>
            </div>
           
        </div>
    </div>
</footer>

<style>
.footer {
    margin-top: auto;
    padding: 20px 0;
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-info {
    font-size: 0.9rem;
    color: #6c757d;
}

.footer-info p {
    margin: 0;
}

.footer-links {
    display: flex;
    gap: 20px;
}

.footer-links a {
    color: #6c757d;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s;
}

.footer-links a:hover {
    color: #4CAF50;
}

@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-links {
        justify-content: center;
    }
}
</style> 