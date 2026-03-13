function openModal(mode, user) {
    var modal = document.getElementById("userModal");
    var title = document.getElementById("modalTitle");
    var form = document.querySelector(".modal-form");
    var actionInput = document.getElementById("formAction");
    var userIdInput = document.getElementById("userId");

    // Inputs
    var nameInput = document.getElementById("nameInput");
    var usernameInput = document.getElementById("usernameInput");
    var roleInput = document.getElementById("roleInput");
    var passwordInput = document.getElementById("passwordInput");
    var passwordHint = document.getElementById("passwordHint");

    modal.style.display = "block";

    if (mode === 'add') {
        title.innerText = "Nuevo Usuario";
        actionInput.value = "add";
        userIdInput.value = "";

        // Reset form fields
        nameInput.value = "";
        usernameInput.value = "";
        usernameInput.readOnly = false;
        roleInput.value = "votante";
        passwordInput.value = "";
        passwordInput.required = true;
        passwordHint.innerText = "*";

    } else if (mode === 'edit') {
        // Parse user if string (though it should be object from onclick)
        if (typeof user === 'string') {
            user = JSON.parse(user);
        }

        title.innerText = "Editar Usuario (" + user.username + ")";
        actionInput.value = "edit";
        userIdInput.value = user.id;

        nameInput.value = user.name || ""; // Handle nulls
        usernameInput.value = user.username;
        usernameInput.readOnly = true; // Cannot edit username
        roleInput.value = user.role;

        passwordInput.value = "";
        passwordInput.required = false;
        passwordHint.innerText = "(Dejar en blanco para mantener)";
    }
}

function closeModal() {
    document.getElementById("userModal").style.display = "none";
}

// Close if clicking outside
window.onclick = function (event) {
    if (event.target == document.getElementById("userModal")) {
        closeModal();
    }
}
