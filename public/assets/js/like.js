    document.getElementById('like-button').addEventListener('click', function() {
        var postId = this.getAttribute('data-post-id'); // Utilise un data-* pour injecter postId

        // Vérifier si l'utilisateur a déjà liké l'article via localStorage
        let likedArticles = localStorage.getItem('liked_articles');
        likedArticles = likedArticles ? JSON.parse(likedArticles) : [];

        // Convertir en nombre pour éviter des problèmes de type
        if (likedArticles.includes(postId.toString())) {
            alert("Vous avez déjà liké cet article");
            return; // Empêcher l'envoi de la requête si déjà liké
        }

        // Si l'article n'a pas encore été liké, on envoie la requête
        fetch('/article/' + postId + '/post/like', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.likes) {
                document.getElementById('like-button').innerHTML = data.likes + ' <i class="fas fa-thumbs-up"></i>';
                // Mettre à jour localStorage avec le nouvel article liké
                likedArticles.push(postId.toString());
                localStorage.setItem('liked_articles', JSON.stringify(likedArticles));
            } else if (data.error) {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert("Une erreur est survenue lors de la requête. Veuillez réessayer.");
        });
    });

