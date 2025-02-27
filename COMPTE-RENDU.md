Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : TEMPS

**Choix des méthodes à analyser** :

- `getMeta` 4.04
- `getReviews` 8.79
- `getMetas` 4.25



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : TEMPS

**Temps consommé par `le global`** 

- **Avant** 30.2

- **Methode Baptiste** 27.8

- **Après** 28.9


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 28.9

- **Après** 21.7 au temps récupéré en classe (impossible chez moi quoi que je fasse j'étais en timeout)


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 28.9

```sql
-- SELECT * FROM wp_usermeta
```

- **Après** 27.6

```sql
-- SELECT meta_value FROM wp_usermeta where user_id = :id AND meta_key = :key
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 27.6

```sql
-- SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 25.9

```sql
-- SELECT round(AVG(meta_value)) as rating, COUNT(meta_value) as count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 25.9

```sql
-- REQ SQL DE BASE
```

- **Après** 21.7

```sql
-- SELECT
         post.ID,
         post.post_author,
         post.post_title,
         MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
         CAST(surfaceData.meta_value AS UNSIGNED) AS surface,
         CAST(roomData.meta_value AS UNSIGNED) AS room,
         CAST(bathRoomData.meta_value AS UNSIGNED) AS bathRoom,
         typeData.meta_value AS type
      FROM
         tp.wp_posts AS post
            INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
            AND priceData.meta_key = 'price'
            INNER JOIN tp.wp_postmeta AS surfaceData ON post.ID = surfaceData.post_id
            AND surfaceData.meta_key = 'surface'
            INNER JOIN tp.wp_postmeta AS roomData ON post.ID = roomData.post_id
            AND roomData.meta_key = 'bedrooms_count'
            INNER JOIN tp.wp_postmeta AS bathRoomData ON post.ID = bathRoomData.post_id
            AND bathRoomData.meta_key = 'bathrooms_count'
            INNER JOIN tp.wp_postmeta AS typeData ON post.ID = typeData.post_id
            AND typeData.meta_key = 'type'
      WHERE
         post.post_type = 'room' + les autres where
```



## Question 5 : Réduction du nombre de requêtes SQL pour `METHOD`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | NOMBRE    | NOMBRE    |
 | Temps de `METHOD`            | TEMPS     | TEMPS     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | NOMBRE    | NOMBRE    |
| Temps de chargement global   | TEMPS     | TEMPS     |

**Requête SQL**

```SQL
-- GIGA REQUÊTE
-- INDENTATION PROPRE ET COMMENTAIRES SERONT APPRÉCIÉS MERCI !
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`

**Requête SQL d'ajout des indexes** 

```sql
-- REQ SQL CREATION INDEXES
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | TEMPS       | TEMPS        |
| `OneRequestService`            | TEMPS       | TEMPS        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `rooms` (1 200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
