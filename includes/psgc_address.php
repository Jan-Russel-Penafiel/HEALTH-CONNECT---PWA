<?php
/**
 * PSGC Address Component
 * Include this file and call renderPsgcAddress($prefix) to render the address fields
 * $prefix is used to create unique IDs when multiple address forms exist on one page
 */

function renderPsgcAddress($prefix = '', $required = true, $existingAddress = '') {
    $reqAttr = $required ? 'required' : '';
    $prefixId = $prefix ? $prefix . '_' : '';
    $prefixName = $prefix ? $prefix . '_' : '';
    ?>
    <div class="psgc-address-container" data-prefix="<?php echo $prefixId; ?>">
        <div class="form-row psgc-row">
            <div class="form-group">
                <label for="<?php echo $prefixId; ?>province">Province <span class="required">*</span></label>
                <select id="<?php echo $prefixId; ?>province" name="<?php echo $prefixName; ?>province" class="form-control psgc-province" <?php echo $reqAttr; ?>>
                    <option value="">Select Province</option>
                </select>
            </div>
            <div class="form-group">
                <label for="<?php echo $prefixId; ?>municipality">City/Municipality <span class="required">*</span></label>
                <select id="<?php echo $prefixId; ?>municipality" name="<?php echo $prefixName; ?>municipality" class="form-control psgc-municipality" <?php echo $reqAttr; ?> disabled>
                    <option value="">Select City/Municipality</option>
                </select>
            </div>
        </div>
        <div class="form-row psgc-row">
            <div class="form-group">
                <label for="<?php echo $prefixId; ?>barangay">Barangay <span class="required">*</span></label>
                <select id="<?php echo $prefixId; ?>barangay" name="<?php echo $prefixName; ?>barangay" class="form-control psgc-barangay" <?php echo $reqAttr; ?> disabled>
                    <option value="">Select Barangay</option>
                </select>
            </div>
            <div class="form-group">
                <label for="<?php echo $prefixId; ?>purok">Purok/Street/House No.</label>
                <input type="text" id="<?php echo $prefixId; ?>purok" name="<?php echo $prefixName; ?>purok" class="form-control psgc-purok" placeholder="Enter Purok/Street/House No.">
            </div>
        </div>
        <!-- Hidden field to store complete address -->
        <input type="hidden" id="<?php echo $prefixId; ?>address" name="address" class="psgc-full-address" value="<?php echo htmlspecialchars($existingAddress); ?>">
    </div>
    <?php
}

function renderPsgcAddressStyles() {
    ?>
    <style>
        .psgc-address-container {
            width: 100%;
        }
        .psgc-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 768px) {
            .psgc-row {
                grid-template-columns: 1fr;
            }
        }
        .psgc-address-container .form-group {
            margin-bottom: 15px;
        }
        .psgc-address-container select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .psgc-address-container .required {
            color: #dc3545;
        }
        .psgc-loading {
            position: relative;
        }
        .psgc-loading::after {
            content: '';
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #ddd;
            border-top-color: #4a90e2;
            border-radius: 50%;
            animation: psgc-spin 0.8s linear infinite;
        }
        @keyframes psgc-spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
    <?php
}

function renderPsgcAddressScript() {
    ?>
    <script>
    // PSGC API Integration
    const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
    
    class PSGCAddress {
        constructor(container) {
            this.container = container;
            this.prefix = container.dataset.prefix || '';
            this.provinceSelect = container.querySelector('.psgc-province');
            this.municipalitySelect = container.querySelector('.psgc-municipality');
            this.barangaySelect = container.querySelector('.psgc-barangay');
            this.purokInput = container.querySelector('.psgc-purok');
            this.fullAddressInput = container.querySelector('.psgc-full-address');
            
            this.selectedProvince = '';
            this.selectedMunicipality = '';
            this.selectedBarangay = '';
            
            this.init();
        }
        
        async init() {
            await this.loadProvinces();
            this.bindEvents();
        }
        
        async loadProvinces() {
            try {
                this.setLoading(this.provinceSelect, true);
                const response = await fetch(`${PSGC_API_BASE}/provinces.json`);
                const provinces = await response.json();
                
                // Sort provinces alphabetically
                provinces.sort((a, b) => a.name.localeCompare(b.name));
                
                this.provinceSelect.innerHTML = '<option value="">Select Province</option>';
                provinces.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province.code;
                    option.textContent = province.name;
                    option.dataset.name = province.name;
                    this.provinceSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading provinces:', error);
                this.provinceSelect.innerHTML = '<option value="">Error loading provinces</option>';
            } finally {
                this.setLoading(this.provinceSelect, false);
            }
        }
        
        async loadMunicipalities(provinceCode) {
            try {
                this.setLoading(this.municipalitySelect, true);
                this.municipalitySelect.disabled = true;
                this.barangaySelect.disabled = true;
                
                const response = await fetch(`${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities.json`);
                const municipalities = await response.json();
                
                // Sort municipalities alphabetically
                municipalities.sort((a, b) => a.name.localeCompare(b.name));
                
                this.municipalitySelect.innerHTML = '<option value="">Select City/Municipality</option>';
                municipalities.forEach(municipality => {
                    const option = document.createElement('option');
                    option.value = municipality.code;
                    option.textContent = municipality.name;
                    option.dataset.name = municipality.name;
                    this.municipalitySelect.appendChild(option);
                });
                
                this.municipalitySelect.disabled = false;
                this.barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            } catch (error) {
                console.error('Error loading municipalities:', error);
                this.municipalitySelect.innerHTML = '<option value="">Error loading municipalities</option>';
            } finally {
                this.setLoading(this.municipalitySelect, false);
            }
        }
        
        async loadBarangays(municipalityCode) {
            try {
                this.setLoading(this.barangaySelect, true);
                this.barangaySelect.disabled = true;
                
                const response = await fetch(`${PSGC_API_BASE}/cities-municipalities/${municipalityCode}/barangays.json`);
                const barangays = await response.json();
                
                // Sort barangays alphabetically
                barangays.sort((a, b) => a.name.localeCompare(b.name));
                
                this.barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                barangays.forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay.code;
                    option.textContent = barangay.name;
                    option.dataset.name = barangay.name;
                    this.barangaySelect.appendChild(option);
                });
                
                this.barangaySelect.disabled = false;
            } catch (error) {
                console.error('Error loading barangays:', error);
                this.barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
            } finally {
                this.setLoading(this.barangaySelect, false);
            }
        }
        
        bindEvents() {
            this.provinceSelect.addEventListener('change', async (e) => {
                const provinceCode = e.target.value;
                const selectedOption = e.target.options[e.target.selectedIndex];
                this.selectedProvince = selectedOption.dataset.name || '';
                
                // Reset dependent fields
                this.municipalitySelect.innerHTML = '<option value="">Select City/Municipality</option>';
                this.municipalitySelect.disabled = true;
                this.barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                this.barangaySelect.disabled = true;
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                
                if (provinceCode) {
                    await this.loadMunicipalities(provinceCode);
                }
                this.updateFullAddress();
            });
            
            this.municipalitySelect.addEventListener('change', async (e) => {
                const municipalityCode = e.target.value;
                const selectedOption = e.target.options[e.target.selectedIndex];
                this.selectedMunicipality = selectedOption.dataset.name || '';
                
                // Reset barangay
                this.barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                this.barangaySelect.disabled = true;
                this.selectedBarangay = '';
                
                if (municipalityCode) {
                    await this.loadBarangays(municipalityCode);
                }
                this.updateFullAddress();
            });
            
            this.barangaySelect.addEventListener('change', (e) => {
                const selectedOption = e.target.options[e.target.selectedIndex];
                this.selectedBarangay = selectedOption.dataset.name || '';
                this.updateFullAddress();
            });
            
            this.purokInput.addEventListener('input', () => {
                this.updateFullAddress();
            });
        }
        
        updateFullAddress() {
            const parts = [];
            
            if (this.purokInput.value.trim()) {
                parts.push(this.purokInput.value.trim());
            }
            if (this.selectedBarangay) {
                parts.push('Brgy. ' + this.selectedBarangay);
            }
            if (this.selectedMunicipality) {
                parts.push(this.selectedMunicipality);
            }
            if (this.selectedProvince) {
                parts.push(this.selectedProvince);
            }
            
            this.fullAddressInput.value = parts.join(', ');
        }
        
        setLoading(element, isLoading) {
            if (isLoading) {
                element.parentElement.classList.add('psgc-loading');
            } else {
                element.parentElement.classList.remove('psgc-loading');
            }
        }
        
        // Method to set values programmatically (for edit forms)
        async setValues(provinceCode, municipalityCode, barangayCode, purok) {
            if (provinceCode) {
                this.provinceSelect.value = provinceCode;
                const provinceOption = this.provinceSelect.options[this.provinceSelect.selectedIndex];
                this.selectedProvince = provinceOption?.dataset?.name || '';
                
                await this.loadMunicipalities(provinceCode);
                
                if (municipalityCode) {
                    this.municipalitySelect.value = municipalityCode;
                    const municipalityOption = this.municipalitySelect.options[this.municipalitySelect.selectedIndex];
                    this.selectedMunicipality = municipalityOption?.dataset?.name || '';
                    
                    await this.loadBarangays(municipalityCode);
                    
                    if (barangayCode) {
                        this.barangaySelect.value = barangayCode;
                        const barangayOption = this.barangaySelect.options[this.barangaySelect.selectedIndex];
                        this.selectedBarangay = barangayOption?.dataset?.name || '';
                    }
                }
            }
            
            if (purok) {
                this.purokInput.value = purok;
            }
            
            this.updateFullAddress();
        }
    }
    
    // Initialize all PSGC address containers on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.psgc-address-container').forEach(container => {
            new PSGCAddress(container);
        });
    });
    
    // Function to initialize PSGC for dynamically added containers
    function initPSGCAddress(container) {
        return new PSGCAddress(container);
    }
    </script>
    <?php
}
?>
