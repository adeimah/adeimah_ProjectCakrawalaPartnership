// File: project uas_patnership/pipeline.js

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inisialisasi SortableJS (Drag and Drop)
    const columns = document.querySelectorAll('.pipeline-cards');
    columns.forEach(column => {
        new Sortable(column, {
            group: 'shared', 
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                const itemId = evt.item.dataset.id;
                const newStatus = evt.to.closest('.pipeline-column').dataset.status;
                
                // Update status via AJAX
                updateCardStatus(itemId, newStatus);
                
                // Update hitungan kartu di kolom
                updateColumnCounts();
            },
        });
    });

    // 2. Fungsi untuk Update Hitungan Kartu
    function updateColumnCounts() {
        document.querySelectorAll('.pipeline-column').forEach(column => {
            const statusKey = column.dataset.status;
            // Hanya hitung kartu yang terlihat (display: block)
            const visibleCards = column.querySelectorAll('.pipeline-card[style*="display: block"], .pipeline-card:not([style*="display: none"])');
            document.getElementById(`count-${statusKey}`).textContent = visibleCards.length;
        });
    }

    // Panggil saat inisialisasi
    updateColumnCounts();

    // 3. Implementasi Filter PIC
    window.filterCards = function(selectedPic) {
        let allCards = document.querySelectorAll('.pipeline-card');
        
        allCards.forEach(card => {
            // Data JSON di pipeline_tracking.php sudah dijamin bersih dari backslashes
            const cardData = JSON.parse(card.dataset.card);
            
            // Tampilkan jika tidak ada filter (Semua PIC) ATAU jika PIC kartu cocok
            if (selectedPic === "" || cardData.contact_person === selectedPic) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Perbarui hitungan setelah filter diaplikasikan
        updateColumnCounts();
    }


    // 4. Fungsi untuk Update Status Kartu via AJAX
    function updateCardStatus(cardId, newStatus) {
        const userId = currentUserId; 
        
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=move&id=${cardId}&status=${newStatus}&last_edited_by_user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Kartu berhasil dipindahkan ke ${newStatus.replace('_', ' ').toUpperCase()}.`, 'success');
                setTimeout(() => window.location.reload(), 500); 
            } else {
                showNotification(`Gagal memindahkan kartu: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan koneksi saat memindahkan kartu.', 'error');
        });
    }

    // 5. Submit Form (Tambah/Edit) via AJAX
    document.getElementById('cardForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const cardId = document.getElementById('cardId').value;
        const userId = currentUserId;
        
        formData.append('action', cardId ? 'edit' : 'add');
        formData.append('last_edited_by_user_id', userId);
        
        if (cardId) {
             formData.append('id', cardId);
        } else {
             formData.append('created_by_user_id', userId);
        }
        
        if (document.getElementById('fileInput').files.length === 0) {
            formData.delete('document');
        }

        fetch('api.php', {
            method: 'POST',
            body: formData 
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(cardId ? 'Kartu berhasil diperbarui!' : 'Kartu baru berhasil ditambahkan!', 'success');
                closeModal();
                setTimeout(() => window.location.reload(), 500); 
            } else {
                showNotification(`Gagal menyimpan: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan koneksi saat menyimpan kartu.', 'error');
        });
    });
    
    // 6. Delete Card
    window.deleteCard = function() {
        if (!confirm('Apakah Anda yakin ingin menghapus kartu ini?')) return;
        
        const cardId = document.getElementById('cardId').value;
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&id=${cardId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Kartu berhasil dihapus!', 'success');
                closeModal();
                setTimeout(() => window.location.reload(), 500); 
            } else {
                showNotification(`Gagal menghapus: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan koneksi saat menghapus kartu.', 'error');
        });
    }
    
    // 7. Delete File
    window.deleteFile = function() {
        if (!confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) return;
        
        const cardId = document.getElementById('cardId').value;
        const userId = currentUserId;
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_file&id=${cardId}&last_edited_by_user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Dokumen berhasil dihapus!', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                showNotification(`Gagal menghapus dokumen: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan koneksi saat menghapus dokumen.', 'error');
        });
    }

    // 8. Modal & Form Handler (Dipastikan Berjalan dengan Baik)
    const cardModal = document.getElementById('cardModal');
    const form = document.getElementById('cardForm');
    const detailView = document.getElementById('detailView');
    const deleteBtn = document.getElementById('deleteCardBtn');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const formActions = document.getElementById('formActions');
    
    window.openModal = function(statusKey, cardJson) {
        // Reset form
        form.reset();
        document.getElementById('cardId').value = '';
        document.getElementById('fileName').textContent = 'Belum ada file dipilih';
        document.getElementById('fileInput').value = '';
        document.getElementById('currentFileDisplay').style.display = 'none';
        deleteBtn.style.display = 'none';

        if (cardJson) {
            // Mode Detail/Edit
            const card = JSON.parse(cardJson);
            document.getElementById('cardId').value = card.id;
            document.getElementById('cardStatus').value = card.status;
            
            // Isi form untuk nanti Edit
            document.getElementById('companyName').value = card.company_name;
            document.getElementById('address').value = card.address;
            document.getElementById('contactPerson').value = card.contact_person;
            document.getElementById('phone').value = card.phone;
            document.getElementById('email').value = card.email;
            document.getElementById('notes').value = card.notes;
            
            // Tampilkan Detail View secara default
            showDetailView(card);
            
            deleteBtn.style.display = 'block';
            
            if (card.status === 'signed') {
                 fileUploadSection.style.display = 'block';
            } else {
                 fileUploadSection.style.display = 'none';
            }
            
            if (card.document_path) {
                document.getElementById('currentFileName').textContent = card.document_path.split('/').pop();
                document.getElementById('currentFileLink').href = card.document_path;
                // Menggunakan flex untuk menampilkan ikon/teks secara horizontal
                document.getElementById('currentFileDisplay').style.display = 'flex'; 
            } else {
                 document.getElementById('currentFileDisplay').style.display = 'none';
            }
            
        } else {
            // Mode Tambah Baru (dipanggil dari tombol "Tambah Baru" di atas)
            document.getElementById('modalTitle').textContent = 'Tambah Pipeline Baru';
            document.getElementById('cardStatus').value = statusKey || 'daily_canvasing'; // Default ke daily_canvasing
            formActions.querySelector('.btn-primary').textContent = 'Simpan';
            
            // Tampilkan Form View
            showFormView();
            
            // Sembunyikan upload file karena status default bukan signed
            fileUploadSection.style.display = 'none';
        }
        
        cardModal.classList.add('is-open');
    }
    
    window.closeModal = function() {
        cardModal.classList.remove('is-open');
    }
    
    function showDetailView(card) {
        document.getElementById('modalTitle').textContent = `Detail: ${card.company_name}`;
        form.style.display = 'none';
        detailView.style.display = 'block';
        
        const updatedDate = new Date(card.updated_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        
        const detailHtml = `
            <div class="detail-item"><strong>Status:</strong> ${card.status.replace('_', ' ').toUpperCase()}</div>
            <div class="detail-item"><strong>Nama Perusahaan:</strong> ${card.company_name}</div>
            <div class="detail-item"><strong>Alamat:</strong> ${card.address}</div>
            <div class="detail-item"><strong>PIC:</strong> ${card.contact_person}</div>
            <div class="detail-item"><strong>Telepon:</strong> ${card.phone}</div>
            <div class="detail-item"><strong>Email:</strong> ${card.email || '-'}</div>
            <div class="detail-item"><strong>Keterangan:</strong> ${card.notes || '-'}</div>
            ${card.document_path ? `<div class="detail-item"><strong>Dokumen:</strong> <a href="${card.document_path}" target="_blank" class="detail-link"><i class="fas fa-file-pdf"></i> Lihat Dokumen</a></div>` : ''}
            <div class="detail-item"><strong>Terakhir Diedit:</strong> ${card.last_editor_name || 'N/A'} (${updatedDate})</div>
        `;
        detailView.querySelector('.card-detail-view').innerHTML = detailHtml;
    }
    
    function showFormView() {
        detailView.style.display = 'none';
        form.style.display = 'block';
    }
    
    window.switchToEditMode = function() {
        document.getElementById('modalTitle').textContent = `Edit: ${document.getElementById('companyName').value}`;
        showFormView();
        
        formActions.querySelector('.btn-primary').textContent = 'Simpan Perubahan';
        
        const currentStatus = document.getElementById('cardStatus').value;
         if (currentStatus === 'signed') {
            fileUploadSection.style.display = 'block';
        } else {
            fileUploadSection.style.display = 'none';
        }
    }

    // 9. File Input Change Handler
    document.getElementById('fileInput').addEventListener('change', function() {
        const fileNameDisplay = document.getElementById('fileName');
        if (this.files.length > 0) {
            fileNameDisplay.textContent = this.files[0].name;
            if (this.files[0].type !== 'application/pdf') {
                alert('Hanya file PDF yang diizinkan!');
                this.value = '';
                fileNameDisplay.textContent = 'Belum ada file dipilih';
            }
        } else {
            fileNameDisplay.textContent = 'Belum ada file dipilih';
        }
    });

    // 10. Popup Notification
    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.style.backgroundColor = type === 'success' ? '#28a745' : '#dc3545';
        notification.style.opacity = 1;
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.opacity = 0;
            setTimeout(() => { notification.style.display = 'none'; }, 300);
        }, 3000);
    }
});