const taskForm = document.getElementById("taskForm");
const taskInput = document.getElementById("taskInput");
const categorySelect = document.getElementById("categorySelect");
const filterSelect = document.getElementById("filterSelect"); 
const taskList = document.getElementById("taskList");

let tasks = [];

function addTask(event) {
    event.preventDefault();
    const newTask = {
        id: Date.now(),
        text: taskInput.value,
        category: categorySelect.value,
        completed: false,
        isEditing: false
    };
    tasks.push(newTask);
    taskInput.value = '';
    renderTasks();
}

function handleTaskClick(event) {
    const target = event.target;
    const li = target.closest('li');
    if (!li) return;
    const taskId = Number(li.dataset.id);

    if (target.classList.contains('deleteBtn')) {
        tasks = tasks.filter(t => t.id !== taskId);
    } 
    else if (target.classList.contains('editBtn')) {
        tasks = tasks.map(t => t.id === taskId ? { ...t, isEditing: true } : t);
    }
    else if (target.classList.contains('saveBtn')) {
        const inputField = li.querySelector('.edit-input');
        tasks = tasks.map(t => t.id === taskId ? { ...t, text: inputField.value, isEditing: false } : t);
    }
    else if (target.classList.contains('task-text')) {
        tasks = tasks.map(t => t.id === taskId ? { ...t, completed: !t.completed } : t);
    }
    renderTasks();
}

function renderTasks() {
    taskList.innerHTML = '';
    
    const currentFilter = filterSelect.value;

    const filteredTasks = tasks.filter(task => {
        if (currentFilter === "All") return true;
        return task.category === currentFilter;
    });

    filteredTasks.forEach(task => {
        const li = document.createElement('li');
        li.dataset.id = task.id;
        if (task.completed) li.classList.add('completed');

        if (task.isEditing) {
            li.innerHTML = `
                <input type="text" class="edit-input" value="${task.text}">
                <button class="saveBtn">Save</button>
            `;
            setTimeout(() => li.querySelector('.edit-input').focus(), 1);
        } else {
            li.innerHTML = `
                <div class="task-content">
                    <span class="category-badge badge-${task.category.toLowerCase()}">${task.category}</span>
                    <span class="task-text">${task.text}</span>
                </div>
                <div class="actions">
                    <button class="editBtn">Edit</button>
                    <button class="deleteBtn">Delete</button>
                </div>
            `;
        }
        taskList.appendChild(li);
    });
}

taskForm.addEventListener('submit', addTask);
taskList.addEventListener('click', handleTaskClick);
filterSelect.addEventListener('change', renderTasks);