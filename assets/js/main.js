document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            document.querySelector('.main-nav').classList.toggle('active');
        });
    }

    // Image gallery on listing detail page
    const mainImage = document.querySelector('.main-image');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (mainImage && thumbnails.length > 0) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                // Update main image
                mainImage.src = this.src;
                
                // Update active thumbnail
                thumbnails.forEach(thumb => thumb.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    // Advanced search form - dynamic model dropdown based on selected make
    const makeSelect = document.getElementById('make_id');
    const modelSelect = document.getElementById('model_id');
    
    if (makeSelect && modelSelect) {
        makeSelect.addEventListener('change', function() {
            const makeId = this.value;
            
            // Clear current options
            modelSelect.innerHTML = '<option value="">Alle Modelle</option>';
            
            if (makeId) {
                // Fetch models for selected make
                fetch(`get-models.php?make_id=${makeId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.model_id;
                            option.textContent = model.name;
                            modelSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching models:', error));
            }
        });
    }

    // Favorite toggle
    const favoriteButtons = document.querySelectorAll('.favorite-toggle');
    
    if (favoriteButtons.length > 0) {
        favoriteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const partId = this.dataset.partId;
                const isFavorite = this.classList.contains('active');
                
                fetch('toggle-favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `part_id=${partId}&action=${isFavorite ? 'remove' : 'add'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.toggle('active');
                        
                        // Update icon
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.className = isFavorite ? 'far fa-heart' : 'fas fa-heart';
                        }
                    }
                })
                .catch(error => console.error('Error toggling favorite:', error));
            });
        });
    }

    // Image preview for listing creation
    const imageInput = document.getElementById('images');
    const imagePreviewContainer = document.getElementById('image-preview');
    
    if (imageInput && imagePreviewContainer) {
        imageInput.addEventListener('change', function() {
            // Clear previous previews
            imagePreviewContainer.innerHTML = '';
            
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    if (!file.type.match('image.*')) {
                        return;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        previewDiv.appendChild(img);
                        imagePreviewContainer.appendChild(previewDiv);
                    };
                    
                    reader.readAsDataURL(file);
                });
            }
        });
    }

    // Message form validation
    const messageForm = document.getElementById('message-form');
    
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            const messageInput = document.getElementById('message');
            
            if (!messageInput.value.trim()) {
                e.preventDefault();
                alert('Bitte geben Sie eine Nachricht ein.');
            }
        });
    }
});