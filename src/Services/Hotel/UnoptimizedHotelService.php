<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use App\Common\PDOSingleton;
use Exception;
use PDO;

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  
  private PDO $db;
  private Timers $timer;

  protected function __construct () {
    parent::__construct( new RoomService() );
    $this->timer = Timers::getInstance();
    $this->db = PDOSingleton::get();
  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    return $this->db;
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
    $time = $this->timer->startTimer("Meta1");
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT meta_value FROM wp_usermeta where user_id = :id AND meta_key = :key");
    $stmt->execute([
      "id"=>$userId,
      "key"=>$key
    ]);
    
    $this->timer->endTimer("Meta1", $time);
    return $stmt->fetchColumn();
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
    $time = $this->timer->startTimer("Meta2");
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];
    $this->timer->endTimer("Meta2", $time);
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    $time = $this->timer->startTimer("Review");
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( "SELECT round(AVG(meta_value)) as rating, COUNT(meta_value) as count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $this->timer->endTimer("Review", $time);
    return $reviews[0];
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    // On charge toutes les chambres de l'hôtel

    $whereClauses = [];

      if ( isset( $args['surface']['min'] ))
        $whereClauses[] = 'surface >= :surfaceMin';
      
      if ( isset( $args['surface']['max'] ))
        $whereClauses[] = 'surface >= :surfaceMax';
      
      if ( isset( $args['price']['min'] ))
        $whereClauses[] = 'price >= :priceMin';
      
      if ( isset( $args['price']['max'] ))
        $whereClauses[] = 'price >= :priceMax';
      
      if ( isset( $args['rooms'] ))
        $whereClauses[] = 'room >= :nbRoom';
      
      if ( isset( $args['bathRooms'] ))
        $whereClauses[] = 'bathRoom >= :nbBathRoom';
      
      if ( isset( $args['types'] ))
        $whereClauses[] = 'type == :type';

      $query = "SELECT
         post.ID,
         post.post_author,
         post.post_title,
         MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
         CAST(surfaceData.meta_value AS UNSIGNED) AS surface,
         CAST(roomData.meta_value AS UNSIGNED) AS room,
         CAST(bathRoomData.meta_value AS UNSIGNED) AS bathRoom,
         CAST(typeData.meta_value AS UNSIGNED) AS type
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
         post.post_type = 'room'";

      if ( count($whereClauses) > 0 )
          $query .=  implode( ' AND ', $whereClauses ) . "GROUP BY post.post_author";


      $stmt = $this->getDB()->prepare($query);

      if ( isset( $args['surface']['min'] ))
          $stmt->bindParam('myFilter', $args['surface']['min'], PDO::PARAM_INT);

      if ( isset( $args['surface']['max'] ))
          $whereClauses[] = 'surface >= :surfaceMax';

      if ( isset( $args['price']['min'] ))
          $whereClauses[] = 'price >= :priceMin';

      if ( isset( $args['price']['max'] ))
          $whereClauses[] = 'price >= :priceMax';

      if ( isset( $args['rooms'] ))
          $whereClauses[] = 'room >= :nbRoom';

      if ( isset( $args['bathRooms'] ))
          $whereClauses[] = 'bathRoom >= :nbBathRoom';

      if ( isset( $args['types'] ))
          $whereClauses[] = 'type == :type';

      $stmt->execute( ['hotelId' => $hotel->getId()]);
      $chambrePrixBas = $stmt->fetchAll( PDO::FETCH_ASSOC );
      dump($chambrePrixBas);
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( count( $chambrePrixBas ) < 1 )
      throw new FilterException( "Aucune chambre ne correspond aux critères" );


    $cheapestRoom = $chambrePrixBas[0][0];


    return $cheapestRoom;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $time = $this->timer->startTimer("Convertion");
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
    $this->timer->endTimer("Convertion", $time);
    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}