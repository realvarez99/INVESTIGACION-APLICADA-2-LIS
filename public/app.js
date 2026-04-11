const API_URL = '/api/books';

// Elementos del DOM
const form = document.getElementById('bookForm');
const tableBody = document.getElementById('booksTableBody');
const cancelBtn = document.getElementById('cancelBtn');
const formTitle = document.getElementById('formTitle');
const bookIdInput = document.getElementById('bookId');

document.addEventListener('DOMContentLoaded', fetchBooks);

async function fetchBooks() {
    try {
        const response = await fetch(API_URL);
        const json = await response.json();
        
        if (json.success) {
            renderTable(json.data);
        }
    } catch (error) {
        console.error('Error al cargar libros:', error);
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = bookIdInput.value;
    const isEditing = id !== '';

    const bookData = {
        title: document.getElementById('title').value,
        author: document.getElementById('author').value,
        isbn: document.getElementById('isbn').value,
        quantity: parseInt(document.getElementById('quantity').value),
        year: parseInt(document.getElementById('year').value) || null
    };

    try {
        const url = isEditing ? `${API_URL}/${id}` : API_URL;
        const method = isEditing ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bookData)
        });

        const result = await response.json();

        if (result.success) {
            alert(isEditing ? 'Libro actualizado' : 'Libro creado');
            resetForm();
            fetchBooks();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error al guardar:', error);
    }
});

async function deleteBook(id) {
    if (!confirm('¿Estás seguro de eliminar este libro?')) return;

    try {
        const response = await fetch(`${API_URL}/${id}`, { method: 'DELETE' });
        const result = await response.json();

        if (result.success) {
            fetchBooks();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error al eliminar:', error);
    }
}

function editBook(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const cells = row.querySelectorAll('td');

    bookIdInput.value = id;
    document.getElementById('title').value = cells[1].innerText;
    document.getElementById('author').value = cells[2].innerText;
    document.getElementById('isbn').value = cells[3].innerText;
    document.getElementById('quantity').value = cells[4].innerText;
    document.getElementById('year').value = cells[5].innerText !== 'N/A' ? cells[5].innerText : '';

    formTitle.innerText = 'Editando Libro (ID: ' + id + ')';
    cancelBtn.style.display = 'inline-block';
}

function renderTable(books) {
    tableBody.innerHTML = '';
    books.forEach(book => {
        const tr = document.createElement('tr');
        tr.setAttribute('data-id', book.id);
        tr.innerHTML = `
            <td>${book.id}</td>
            <td>${book.title}</td>
            <td>${book.author}</td>
            <td>${book.isbn}</td>
            <td>${book.quantity}</td>
            <td>${book.year || 'N/A'}</td>
            <td class="actions">
                <button class="btn btn-warning" onclick="editBook(${book.id})">Editar</button>
                <button class="btn btn-danger" onclick="deleteBook(${book.id})">Borrar</button>
            </td>
        `;
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
