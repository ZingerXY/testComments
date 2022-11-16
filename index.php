<?php

$user = null;

if (strtolower($_SERVER["CONTENT_TYPE"]) == 'application/json') {
	header('Content-Type: application/json');
	// exit(json_encode(['result' => uniqid()]));
	$result = ['result' => 'ok'];
	try {
		$result = main();
	} catch (Exception $e) {
		$result = $e->getMessage();
	}
	exit(json_encode($result));
}

function main()
{
	$data = json_decode(file_get_contents('php://input'), true);
	/** Добавляет пользователя */
	if (isParam('addUser')) {
		return addUser($data);
	}

	$user = getUser();
	if (!$user) {
		return ['result' => 'noUser'];
	}

	/** Создает комментарий */
	if (isParam('addComment')) {
		return addComment($data, $user);
	}
	/** Редактирует комментарий */
	if (isParam('editComment')) {
		return editComment($data, $user);
	}
	/** Возвращает комментарии */
	if (isParam('getComments')) {
		return getComments($data);
	}
}

/** Возвращает подключение к базе данных */
function getBd()
{
	include_once 'config.php';

	$pdo = new PDO('mysql:host=' . HOST . ';dbname=' . DB, USER, PASSWORD);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->exec('SET NAMES "utf8"');

	return $pdo;
}

/** Добавляет комментарий */
function addComment($data, $user)
{
	$bd = getBd();

	$addCommentQuery = "INSERT INTO comments (parentId, text, authorId, date) VALUES (:parentId, :text, :authorId, :date)";
	$query = $bd->prepare($addCommentQuery);

	date_default_timezone_set('Europe/Moscow');
	$date = date("Y-m-d H:i:s");

	if ($query->execute(['parentId' => $data['parentId'], 'text' => $data['text'], 'authorId' => $user['id'], 'date' => $date])) {
		return ['result' => 'ok', 'id' => $bd->lastInsertId(), 'authorId' => $user['id'], 'authorName' => $user['name'], 'date' => $date];
	}

	return ['result' => 'error', 'error' => $bd->errorInfo()];
}

/** Редактирует комментарий */
function editComment($data, $user)
{
	$id = $data['id'];
	$comment = getComment($id);
	if ($comment['authorId'] != $user['id']) {
		return ['result' => 'error', 'error' => 'No access'];
	}

	$bd = getBd();
	$editCommentQuery = "UPDATE comments SET text = :newText, date = :date WHERE id = :id;";
	$query = $bd->prepare($editCommentQuery);

	date_default_timezone_set('Europe/Moscow');
	$date = date("Y-m-d H:i:s");
	$newText = $data['newText'];

	if ($query->execute(['newText' => $newText, 'date' => $date, 'id' => $comment['id']])) {
		return ['result' => 'ok', 'date' => $date];
	}

	return ['result' => 'error', 'error' => $bd->errorInfo()];
}

/** Возвращает комментарии */
function getComments()
{
	$bd = getBd();
	$getCommentsQuery = "SELECT comments.id, parentId, text, authorId, users.name authorName, date FROM comments LEFT JOIN users ON users.id = comments.authorId;";
	$resultQuery = $bd->query($getCommentsQuery);
	return $resultQuery->fetchAll(PDO::FETCH_ASSOC);
}

/** Возвращает комментарий по ID */
function getComment($id)
{
	$bd = getBd();
	$getCommentQuery = <<<SQL
SELECT comments.id, parentId, text, authorId, users.name authorName, date 
FROM comments LEFT JOIN users ON users.id = comments.authorId 
WHERE comments.id = :id;
SQL;
	$query = $bd->prepare($getCommentQuery);
	$query->execute(['id' => $id]);
	return $query->fetch(PDO::FETCH_ASSOC);
}

/** Добавляет пользователя */
function addUser($data)
{
	$bd = getBd();

	$name = 'Anonymous';
	if (trim($data['name'])) {
		$name = trim($data['name']);
	}

	$addCommentQuery = "INSERT INTO users (name, userCode) VALUES (:name, :userCode)";
	$query = $bd->prepare($addCommentQuery);

	$userCode = uniqid();
	if ($query->execute(['name' => $name, 'userCode' => $userCode])) {
		setcookie("userCode", $userCode, time() + 60 * 60 * 24 * 365);
		return ['result' => 'ok', 'id' => $bd->lastInsertId()];
	}

	return ['result' => 'error', 'error' => $bd->errorInfo()];
}

/** Проверять что есть GET параметр с заданным именем */
function isParam($name)
{
	return isset($_GET[$name]);
}
/** Возвращает пользователя по коду из куки */
function getUser()
{
	if (!isset($_COOKIE["userCode"])) {
		return false;
	}

	$bd = getBd();
	$getUserQuery = "SELECT id, name, userCode FROM users WHERE userCode = :userCode;";
	$query = $bd->prepare($getUserQuery);
	$userCode = $_COOKIE["userCode"];

	if ($query->execute(['userCode' => $userCode])) {
		setcookie("userCode", $userCode, time() + 60 * 60 * 24 * 365);
		return $query->fetch(PDO::FETCH_ASSOC);
	}

	// return ['result' => 'error', 'error' => $bd->errorInfo()];
	return false;
}

function isLoginUser()
{
	if (isset($_COOKIE["userCode"]) && $user = getUser($_COOKIE["userCode"])) {
		return true;
	}
	return false;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Comments</title>
	<style>
		.conteiner {
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.comments {
			display: flex;
			flex-direction: column;
		}

		.comment {
			display: flex;
			flex-direction: column;
			margin: 5px;
			border: 1px solid #00000080;
			padding: 10px;
			border-radius: 10px;
		}

		.titleComment {
			display: flex;
			justify-content: space-between;
			margin: 2px 10px;
		}

		.author {
			margin-right: 20px;
			font-weight: bold;
		}

		.message {
			display: flex;
			justify-content: space-between;
			margin: 2px 10px;
		}

		.edit {
			margin-left: 10px;
		}

		.onEdit,
		.edit {
			cursor: pointer;
			color: green;
		}

		.send {
			display: flex;
			flex-direction: column;
		}

		.send input {
			margin-top: 3px;
		}

		.comment .comment {
			margin-left: 20px;
		}

		.newUser {
			display: flex;
			flex-direction: column;
		}

		.newUser>input {
			margin-top: 5px;
		}

		.hide {
			display: none;
		}
	</style>
</head>

<body>
	<div class="conteiner">
		<div class="comments">
		</div>
	</div>
	<script>
		getComments();

		/** Проверяет что пользователь есть */
		function checkUser(data) {
			if (data.result == 'noUser') {
				newUser();
				return false;
			}
			return true;
		}

		/** Создает форму для создания пользователя */
		function newUser() {
			let commentsElement = document.querySelector('div.comments');
			commentsElement.innerHTML = '';

			let newUser = document.createElement('div');
			newUser.classList.add('newUser');

			let welcome = document.createElement('div');
			welcome.classList.add('welcome');
			welcome.innerText = 'Представьтесь пожалуйста:';
			newUser.appendChild(welcome);

			let inputText = document.createElement('input');
			inputText.type = "text";
			inputText.placeholder = 'Введите ваше имя';
			newUser.appendChild(inputText);

			let inputButton = document.createElement('input');
			inputButton.type = "button";
			inputButton.value = "Отправить";
			inputButton.addEventListener('click', addUser);
			newUser.appendChild(inputButton);

			let conteinerElement = document.querySelector('div.conteiner');
			conteinerElement.appendChild(newUser);
		}

		/** Добавляет пользователя */
		function addUser() {
			let addUserElement = this.parentNode;
			let inputName = this.previousElementSibling;
			let name = inputName.value;

			console.log('addUser');
			fetch('index.php?addUser', {
				method: 'POST',
				headers: {
					'content-type': 'application/json'
				},
				body: JSON.stringify({
					name
				})
			}).then(response => response.json()).then(data => {
				if (data.result == 'ok') {
					localStorage['userId'] = data.id;
					addUserElement.remove();
					getComments();
					return;
				}
				console.log(data);
			});
		}

		/** Подгружает комментарии */
		function getComments() {
			fetch('index.php?getComments', {
				headers: {
					'content-type': 'application/json'
				}
			}).then(response => response.json()).then(data => {
				if (!checkUser(data)) return;
				showComments(data);
			});
		}

		function createCommentElement(data, deep) {
			deep = deep || 1;
			console.log(deep, data);
			let comment = document.createElement('div');
			comment.classList.add("comment");
			comment.dataset.id = data.id;
			comment.dataset.parentId = data.parentId;
			comment.dataset.deep = deep;
			/** Заголовок комментария */
			let title = document.createElement('div');
			title.classList.add("titleComment");
			/** Автор комментария в заголовке */
			let author = document.createElement('div');
			author.classList.add("author");
			author.innerText = data.authorName ?? 'Anonymous';
			title.appendChild(author);
			/** Дата комментария создания в заголовке */
			let date = document.createElement('div');
			date.classList.add("date");
			date.innerText = data.date;
			title.appendChild(date);

			comment.appendChild(title);
			/** Блок сообщения комментария */
			let message = document.createElement('div');
			message.classList.add("message");
			/** Текст сообщения комментария */
			let text = document.createElement('div');
			text.classList.add("text");
			text.innerText = data.text;
			message.appendChild(text);

			if (data.authorId == localStorage.userId) {
				let edit = document.createElement('div');
				edit.innerText = 'Редактировать';
				edit.classList.add("edit");
				message.appendChild(edit);

				text.classList.add("onEdit");

				message.addEventListener('click', editComment)

				let editText = document.createElement('input');
				editText.value = data.text;
				editText.classList.add("editComment", "hide");
				editText.type = "text";
				// editText.
				message.appendChild(editText);
			}

			comment.appendChild(message);

			let send = document.createElement('div');
			send.classList.add("send");

			if (data.childs) {
				for (const dataComment of data.childs) {
					let childCommentElement = createCommentElement(dataComment, deep + 1);
					comment.appendChild(childCommentElement);
				}
			}

			if (deep < 10) {
				let textarea = document.createElement('textarea');
				send.appendChild(textarea);
				let input = document.createElement('input');
				input.type = "button";
				input.value = "Отправить";
				input.addEventListener('click', addComment);
				send.appendChild(input);

				comment.appendChild(send);
			}

			return comment;
		}

		/** Редактирует комментарий */
		function editComment() {
			this.removeEventListener('click', editComment);
			let message = this.closest('.message');
			message.childNodes.forEach(e => {
				e.classList.toggle("hide");
			});
			let input = message.querySelector('.editComment')
			input.focus();
			input.addEventListener('blur', endEditComment)
		}

		/** Редактирует комментарий */
		function endEditComment(e) {
			let message = this.closest('.message');
			message.childNodes.forEach(e => {
				e.classList.toggle("hide");
			});
			message.addEventListener('click', editComment);

			let text = message.querySelector('.text');
			if (text.innerText == this.value) {
				return;
			}
			let comment = this.closest('.comment');
			let newText = this.value;


			fetch('index.php?editComment', {
				method: 'POST',
				headers: {
					'content-type': 'application/json'
				},
				body: JSON.stringify({
					id: comment.dataset.id,
					newText
				})
			}).then(response => response.json()).then(data => {
				if (!checkUser(data)) return;
				if (data.result == 'ok') {
					text.innerText = newText;
					let date = comment.querySelector('.date');
					date.innerText = data.date;
					return;
				}
				console.log(data);
			});

		}

		/** Отображает все комментарии */
		function showComments(commentsData) {
			commentsData = commentsData.map(e => {
				let childs = commentsData.filter(f => f.parentId == e.id);
				e.childs = childs;
				return e;
			});
			console.log(commentsData);

			let commentsElement = document.querySelector('div.comments');
			commentsData.forEach(commentData => {
				if (+commentData.parentId) {
					return;
				}
				commentElement = createCommentElement(commentData);
				commentsElement.appendChild(commentElement);
			});
		}

		/** Добавляет комментарий */
		function addComment() {
			let comment = this.closest('.comment');
			let parentId = comment.dataset.id;
			let textarea = this.previousElementSibling;
			let text = textarea.value;
			if (!text.trim().length) {
				return;
			}

			fetch('index.php?addComment', {
				method: 'POST',
				headers: {
					'content-type': 'application/json'
				},
				body: JSON.stringify({
					parentId,
					text
				})
			}).then(response => response.json()).then(data => {
				if (!checkUser(data)) return;
				if (data.result == 'ok') {
					textarea.value = '';
					showNewComment(comment, {
						id: data.id,
						parentId,
						text,
						date: data.date,
						authorName: data.authorName,
						authorId: data.authorId
					});
					return;
				}
				console.log(data);
			});
		}

		/** Выводит новый комментарий */
		function showNewComment(comment, dataNewComment) {
			let childCommentElement = createCommentElement(dataNewComment, +comment.dataset.deep + 1);
			comment.insertBefore(childCommentElement, comment.lastElementChild);
		}
	</script>
</body>

</html>