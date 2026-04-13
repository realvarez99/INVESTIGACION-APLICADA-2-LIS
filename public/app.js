const API_URL = '/api/books';

//Elementos del DOM
const form = document.getElementById('bookForm');
const tableBody = document.getElementById('booksTableBody');
const cancelBtn = document.getElementById('cancelBtn');
const formTitle = document.getElementById('formTitle');
const bookIdInput = document.getElementById('bookId');

let booksCache = [];

document.addEventListener('DOMContentLoaded', fetchBooks);

async function fetchBooks() {
    setTableLoading(true);
    try {
        const response = await fetch(API_URL);

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const json = await response.json();

        if (json.success) {
            booksCache = json.data; 
            renderTable(booksCache);
        } else {
            showTableError('No se pudieron cargar los libros.');
        }
    } catch (error) {
        console.error('Error al cargar libros:', error);
        showTableError('Error de conexión con el servidor.');
    } finally {
        setTableLoading(false);
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const id        = bookIdInput.value;
    const isEditing = id !== '';
    const saveBtn   = document.getElementById('saveBtn');

    const bookData = {
        title:    document.getElementById('title').value.trim(),
        author:   document.getElementById('author').value.trim(),
        isbn:     document.getElementById('isbn').value.trim(),
        quantity: parseInt(document.getElementById('quantity').value),
        year:     parseInt(document.getElementById('year').value) || null,
    };

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const url    = isEditing ? `${API_URL}/${id}` : API_URL;
        const method = isEditing ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bookData),
        });

        const result = await response.json();

        if (result.success) {
            if (isEditing) {
                booksCache = booksCache.map(b =>
                    String(b.id) === String(id) ? { ...b, ...bookData, id: b.id } : b
                );
            } else {
                booksCache.push(result.data);
            }

            renderTable(booksCache);
            resetForm();
            showAlert(isEditing ? 'Libro actualizado correctamente.' : 'Libro creado correctamente.', 'success');
        } else {
            showAlert('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error al guardar:', error);
        showAlert('Error de conexión al guardar.', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Libro';
    }
});

async function deleteBook(id) {
    if (!confirm('¿Estás seguro de eliminar este libro?')) return;

    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
        row.style.opacity    = '0.4';
        row.style.pointerEvents = 'none';
    }

    try {
        const response = await fetch(`${API_URL}/${id}`, { method: 'DELETE' });

        const result = await response.json();

        if (result.success) {
            booksCache = booksCache.filter(b => String(b.id) !== String(id));
            renderTable(booksCache);
            showAlert('Libro eliminado correctamente.', 'success');
        } else {
            if (row) {
                row.style.opacity       = '1';
                row.style.pointerEvents = 'auto';
            }
            showAlert('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error al eliminar:', error);
        if (row) {
            row.style.opacity       = '1';
            row.style.pointerEvents = 'auto';
        }
        showAlert('Error de conexión al eliminar.', 'error');
    }
}

function editBook(id) {

    const book = booksCache.find(b => String(b.id) === String(id));
    if (!book) {
        showAlert('No se encontraron los datos del libro.', 'error');
        return;
    }

    bookIdInput.value = book.id;
    document.getElementById('title').value = book.title;
    document.getElementById('author').value = book.author;
    document.getElementById('isbn').value = book.isbn;
    document.getElementById('quantity').value = book.quantity;
    document.getElementById('year').value = book.year || '';

    formTitle.innerText = `Editando Libro (ID: ${id})`;
    cancelBtn.style.display = 'inline-block';

    document.querySelector('.form-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderTable(books) {
    if (books.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;color:#555;padding:32px;">
                    <i class="fas fa-book-open" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                    No hay libros registrados aún.
                </td>
            </tr>`;
        return;
    }

    tableBody.innerHTML = '';
    books.forEach(book => {
        const tr = document.createElement('tr');
        tr.setAttribute('data-id', book.id);
        tr.innerHTML = `
            <td>${book.id}</td>
            <td>${escapeHtml(book.title)}</td>
            <td>${escapeHtml(book.author)}</td>
            <td>${escapeHtml(book.isbn)}</td>
            <td>${book.quantity}</td>
            <td>${book.year || 'N/A'}</td>
            <td class="actions">
                <button class="btn btn-warning" onclick="editBook(${book.id})">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn btn-danger" onclick="deleteBook(${book.id})">
                    <i class="fas fa-trash"></i> Borrar
                </button>
            </td>`;
        tableBody.appendChild(tr);
    });
}

function resetForm() {
    form.reset();
    bookIdInput.value = '';
    formTitle.innerText = 'Agregar Nuevo Libro';
    cancelBtn.style.display = 'none';
}

cancelBtn.addEventListener('click', resetForm);

function showAlert(message, type) {
    // Buscamos o creamos el contenedor de alertas
    let alertContainer = document.getElementById('appAlert');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'appAlert';
        alertContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;';
        document.body.appendChild(alertContainer);
    }

    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
    alert.style.cssText = 'margin-bottom:8px;display:flex;align-items:center;gap:8px;';
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}`;

    alertContainer.appendChild(alert);

    setTimeout(() => {
        alert.style.transition = 'opacity 0.4s';
        alert.style.opacity    = '0';
        setTimeout(() => alert.remove(), 400);
    }, 3000);
}

function setTableLoading(isLoading) {
    if (isLoading) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#555;">
                    <i class="fas fa-spinner fa-spin" style="font-size:20px;display:block;margin-bottom:8px;"></i>
                    Cargando...
                </td>
            </tr>`;
    }
}

function showTableError(message) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align:center;padding:32px;">
                <span style="color:#f87171;">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                </span>
            </td>
        </tr>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}