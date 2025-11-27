<h1 style="text-align:center">CSV to SQL converter</h1>

<form action="convert_action.php" method="post" enctype="multipart/form-data" style="text-align:center">
    <div>
        <label for="database_name">Введите имя базы данных:</label>
        <br>
        <input name="database_name" id="database_name" type="text" required>
    </div>
    <br><br>
    <div>
        <label for="server_name">Введите имя сервера:</label>
        <br>
        <input name="server_name" id="server_name" type="text">
    </div>
    <br><br>
    <div>
        <label for="database_login">Введите логин пользователя(если база запаролена):</label>
        <br>
        <input name="database_login" id="database_login" type="text">
    </div>
    <br><br>
    <div>
        <label for="database_pass">Введите пароль пользователя(если база запаролена):</label>
        <br>
        <input name="database_pass" id="database_pass" type="text">
    </div>
    <br><br>
    <div>
        <label for="file">Загрузите ваши CSV: :</label>
        <br>
        <input name="file[]" type="file" id="file" accept="text/csv" required multiple/>
    </div>
    <br><br>
    <div>
        <button type="submit" name="send">Загрузить CSV в базу данных</button>
    </div>
</form>
