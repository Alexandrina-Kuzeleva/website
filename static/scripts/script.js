const bookButtons = document.querySelectorAll('.book-button');
        const chapterSelectionDiv = document.getElementById('chapter-selection');
        const chapterButtonsDiv = document.getElementById('chapter-buttons');
        const chapterForm = document.getElementById('chapter-form');
        const bookTitleInput = document.getElementById('book_title');
        const chapterNameInput = document.getElementById('chapter_name');

        // Обработка выбора книги
        bookButtons.forEach(button => {
            button.addEventListener('click', function() {
                const selectedBook = this.dataset.book;
                bookTitleInput.value = selectedBook;

                // Отправляем запрос на сервер для получения списка глав
                fetch(`/get_chapters?book=${selectedBook}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            chapterButtonsDiv.innerHTML = ''; // Очищаем предыдущие главы
                            data.chapters.forEach(chapter => {
                                const chapterButton = document.createElement('button');
                                chapterButton.textContent = chapter;
                                chapterButton.classList.add('chapter-button');
                                chapterButton.addEventListener('click', function() {
                                    chapterNameInput.value = chapter;
                                    chapterForm.style.display = 'block'; // Показываем форму для получения главы
                                });
                                chapterButtonsDiv.appendChild(chapterButton);
                            });
                            chapterSelectionDiv.style.display = 'block'; // Показываем раздел с главами
                        } else {
                            alert('Не удалось загрузить главы для этой книги.');
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при получении списка глав:', error);
                    });
            });
        });

        // Обработка отправки формы для получения содержания главы
        chapterForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Останавливаем стандартное поведение формы

            const formData = new FormData(this);

            fetch('/get_chapter', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const contentDiv = document.getElementById('chapter-content');
                const titleDiv = document.getElementById('chapter-title');
                const textDiv = document.getElementById('chapter-text');

                if (data.status === 'success') {
                    contentDiv.style.display = 'block';
                    titleDiv.textContent = data.chapter_title;
                    textDiv.textContent = data.chapter_text;
                } else {
                    contentDiv.style.display = 'block';
                    titleDiv.textContent = 'Ошибка';
                    textDiv.textContent = data.message;
                }
            })
            .catch(error => {
                console.error('Ошибка при выполнении запроса:', error);
                alert('Ошибка при выполнении запроса.');
            });
        });