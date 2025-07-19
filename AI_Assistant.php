<?php
require_once __DIR__ . '/config.php'; // Ensure config is loaded for $pdo

class AI_Assistant {
    private $apiKey;
    private $pdo;
    private $lastPrompt;

    public function __construct($pdo) {
        $this->apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
        $this->pdo = $pdo;
    }

    public function generateEventRecommendation($prompt) {
        try {
            $this->lastPrompt = $prompt;
            
            if (empty($this->apiKey)) {
                throw new Exception('OpenAI API key not configured');
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are an event planning assistant. Help users create event details based on their description. ' .
                                 'Respond STRICTLY in JSON format. The JSON MUST contain: ' .
                                 'event_title, event_type, description, event_date (YYYY-MM-DD), ' .
                                 'budget_range (an object with min and max properties), ' .
                                 'required_services (an array of service objects, each with "name", "priority", and optional "budget_allocation"). ' .
                                 'If you mention services in your "reasoning", they MUST be included in the "required_services" array. ' .
                                 'Also include "reasoning" (a brief explanation of your choices).'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type' => 'application/json', // Corrected syntax here from ':' to '=>'
                    'Authorization: Bearer ' . $this->apiKey
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object']
                ])
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) throw new Exception('CURL error: ' . $error);

            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                throw new Exception('OpenAI API error: ' . $data['error']['message']);
            }
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new Exception('OpenAI API response missing content.');
            }


            $content = json_decode($data['choices'][0]['message']['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to decode AI response JSON: ' . json_last_error_msg());
            }
            return $this->formatEventData($content);

        } catch (Exception $e) {
            error_log("AI Assistant Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function formatEventData($aiData) {
        $eventTypes = $this->dbFetchAll("SELECT id, type_name FROM event_types");
        $services = $this->dbFetchAll("SELECT id, service_name FROM vendor_services");
        
        $typeMap = array_column($eventTypes, 'id', 'type_name');
        
        // REVISED: Create serviceMap with normalized keys for consistent lookup
        $serviceMap = [];
        foreach ($services as $s) {
            $normalizedDbServiceName = strtolower(str_replace(' ', '_', $s['service_name']));
            $serviceMap[$normalizedDbServiceName] = $s['id'];
        }
        
        $formatted = [
            'event' => [
                'event_type_id' => $typeMap[$aiData['event_type']] ?? null,
                'title' => $aiData['event_title'] ?? 'Untitled Event',
                'description' => $aiData['description'] ?? '',
                'event_date' => $aiData['event_date'] ?? date('Y-m-d', strtotime('+1 month')),
                'budget_min' => $aiData['budget_range']['min'] ?? 0,
                'budget_max' => $aiData['budget_range']['max'] ?? 0,
                'ai_preferences' => json_encode([
                    'generated_prompt' => $this->lastPrompt,
                    'decision_factors' => $aiData['reasoning'] ?? null
                ])
            ],
            'services' => []
        ];

        foreach ($aiData['required_services'] ?? [] as $service) {
            // Ensure 'name' key exists and is not null before proceeding
            if (!isset($service['name']) || $service['name'] === null) {
                error_log("Skipping service due to missing 'name' key in AI response: " . json_encode($service));
                continue; // Skip to the next iteration if 'name' is not set or null
            }

            // Normalize service name before lookup (e.g., lowercase, remove spaces)
            $normalizedServiceName = strtolower(str_replace(' ', '_', $service['name']));
            
            // Check if service exists in our database's service map before adding
            if (isset($serviceMap[$normalizedServiceName])) {
                $formatted['services'][] = [
                    'service_id' => $serviceMap[$normalizedServiceName],
                    'priority' => $service['priority'] ?? 'medium',
                    'budget' => $service['budget_allocation'] ?? null
                ];
            } else {
                error_log("AI suggested service name '" . $service['name'] . "' not found in vendor_services map.");
            }
        }

        // FALLBACK: If AI did not populate 'required_services', try to infer from 'reasoning'/'decision_factors'
        if (empty($formatted['services']) && isset($aiData['reasoning'])) {
            $reasoning = $aiData['reasoning'];
            
            // ADJUSTED: commonServiceKeywords values to match DB service_name for correct normalization
            $commonServiceKeywords = [
                'venue' => 'Ballroom Rental', // Mapping 'Venue Rental' from AI to a specific DB service
                'catering' => 'Buffet Catering',
                'audio-visual' => 'Audio/Visual Equipment', 
                'decor' => 'Venue Decor & Styling',
                'photography' => 'Wedding Photography',
                'videography' => 'Event Videography',
                'music' => 'Live Band Performance',
                'dj' => 'DJ Services',
                'officiant' => 'Wedding Officiants',
                'planning' => 'Full Event Planning'
            ];

            foreach ($commonServiceKeywords as $keyword => $dbServiceName) { // Use $dbServiceName as the exact name from DB
                if (stripos($reasoning, $keyword) !== false) {
                    $normalizedFallbackServiceName = strtolower(str_replace(' ', '_', $dbServiceName)); // Normalize for lookup
                    if (isset($serviceMap[$normalizedFallbackServiceName])) {
                        $existingServiceIds = array_column($formatted['services'], 'service_id');
                        if (!in_array($serviceMap[$normalizedFallbackServiceName], $existingServiceIds)) {
                            $formatted['services'][] = [
                                'service_id' => $serviceMap[$normalizedFallbackServiceName],
                                'priority' => 'medium', // Default priority for inferred
                                'budget' => null // No budget inference from reasoning
                            ];
                        }
                    } else {
                        error_log("Fallback: Inferred service '" . $dbServiceName . "' (normalized: " . $normalizedFallbackServiceName . ") not found in serviceMap.");
                    }
                }
            }
        }
        
        return $formatted;
    }

    /**
     * Get vendor recommendations based on event ID.
     * This method fetches detailed vendor information including services offered.
     * @param int $eventId
     * @return array
     */
    public function getVendorRecommendations($eventId) {
        try {
            // Fetch event details including coordinates (if available) and required services
            $event = $this->dbFetch(
                "SELECT e.*, et.type_name, 
                        ST_X(e.venue_location) AS event_lat, ST_Y(e.venue_location) AS event_lng 
                 FROM events e JOIN event_types et ON e.event_type_id = et.id 
                 WHERE e.id = ?", 
                [$eventId]
            );
            
            if (!$event) {
                return [];
            }

            $requiredServices = $this->dbFetchAll(
                "SELECT service_id FROM event_service_requirements WHERE event_id = ?", 
                [$eventId]
            );
            
            $serviceIds = array_column($requiredServices, 'service_id');
            if (empty($serviceIds)) {
                // If no specific services required, perhaps recommend general event planners or top-rated vendors
                // For now, return empty if no services are explicitly required.
                error_log("No specific services found for event ID " . $eventId . " for vendor recommendation.");
                return []; 
            }

            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            
            // Fetch vendors that offer any of the required services, including their profile image
            // Also fetch services offered by vendor to provide in summary
            $query = "
                SELECT 
                    vp.id, vp.business_name, vp.website, vp.rating, vp.total_reviews, 
                    vp.business_address, vp.business_city, vp.business_state, vp.business_country,
                    vp.service_radius,
                    ST_X(vp.business_location) AS business_lat, ST_Y(vp.business_location) AS business_lng,
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR '; ') AS offered_services_names,
                    AVG(vso.price_range_min) AS avg_min_price,
                    AVG(vso.price_range_max) AS avg_max_price,
                    up.profile_image, -- Fetch profile image from user_profiles
                    (SELECT COUNT(*) FROM vendor_availability 
                     WHERE vendor_id = vp.id 
                     AND date = ? 
                     AND status = 'available') AS availability_score
                FROM vendor_profiles vp
                JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id -- Join to get profile image
                WHERE vso.service_id IN ($placeholders)
                GROUP BY vp.id
                ORDER BY rating DESC, avg_min_price ASC
                LIMIT 10";
            
            $params = array_merge([$event['event_date']], $serviceIds);
            $vendors = $this->dbFetchAll($query, $params);

            // Calculate score for each vendor
            $scoredVendors = array_map(function($vendor) use ($event) {
                $vendor['score'] = $this->calculateVendorScore($vendor, $event);
                return $vendor;
            }, $vendors);

            // Sort by total score in descending order
            usort($scoredVendors, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return $scoredVendors;

        } catch (Exception $e) {
            error_log("Vendor Recommendation Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vendor recommendations based on form data (new implementation).
     * This method directly uses the provided event details from the form input.
     * @param array $eventData An associative array with event details including event_type_id, budget_min, budget_max, event_date, location_string, service_ids.
     * @return array
     */
    public function getVendorRecommendationsFromForm(array $eventData) {
        try {
            $eventDate = $eventData['event_date'];
            $locationString = $eventData['location_string'];
            $serviceIds = $eventData['service_ids'];
            $budgetMin = $eventData['budget_min'];
            $budgetMax = $eventData['budget_max'];

            if (empty($serviceIds)) {
                return []; // No services selected, no recommendations.
            }

            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            
            // Base query to find vendors offering selected services
            $query = "
                SELECT 
                    vp.id, vp.business_name, vp.website, vp.rating, vp.total_reviews, 
                    vp.business_address, vp.business_city, vp.business_state, vp.business_country,
                    vp.service_radius,
                    -- Use ST_X and ST_Y if business_location is POINT type, otherwise just business_lat/lng
                    ST_X(vp.business_location) AS business_lng, 
                    ST_Y(vp.business_location) AS business_lat,
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR '; ') AS offered_services_names,
                    AVG(vso.price_range_min) AS avg_min_price,
                    AVG(vso.price_range_max) AS avg_max_price,
                    up.profile_image, -- Fetch profile image from user_profiles
                    (SELECT COUNT(*) FROM vendor_availability 
                     WHERE vendor_id = vp.id 
                     AND date = ? 
                     AND status = 'available') AS availability_score
                FROM vendor_profiles vp
                JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                WHERE vso.service_id IN ($placeholders)
            ";

            $params = [$eventDate];
            $params = array_merge($params, $serviceIds);

            // Add location filter if location_string is provided (e.g., city, partial address)
            if (!empty($locationString)) {
                 $query .= " AND (vp.business_city LIKE ? OR vp.business_address LIKE ?)";
                 $params[] = '%' . $locationString . '%';
                 $params[] = '%' . $locationString . '%';
            }

            $query .= " GROUP BY vp.id
                         ORDER BY vp.rating DESC, avg_min_price ASC
                         LIMIT 20"; // Limit results to a reasonable number

            $vendors = $this->dbFetchAll($query, $params);

            // Manual scoring based on availability, price, and reviews
            $scoredVendors = array_map(function($vendor) use ($eventDate, $budgetMin, $budgetMax) {
                // This 'event' array is constructed on-the-fly for scoring
                $mockEvent = [
                    'event_date' => $eventDate,
                    'budget_min' => $budgetMin,
                    'budget_max' => $budgetMax,
                    'event_lat' => null, // No event lat/lng from form directly, so location score will be neutral or based on city match
                    'event_lng' => null
                ];
                $vendor['score'] = $this->calculateVendorScore($vendor, $mockEvent);
                return $vendor;
            }, $vendors);

            // Sort by total score in descending order
            usort($scoredVendors, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return $scoredVendors;

        } catch (PDOException $e) {
            error_log("Vendor Recommendation From Form PDO Error: " . $e->getMessage());
            throw new Exception("Database error fetching vendor recommendations. " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Vendor Recommendation From Form General Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * NEW: Get personalized vendor recommendations based on user's recent activity.
     * This method leverages viewed vendors and possibly recent searches to inform recommendations.
     * @param int $userId The ID of the current user.
     * @param int $limit The maximum number of recommendations to return.
     * @return array An array of recommended vendor data.
     */
    public function getPersonalizedVendorRecommendations($userId, $limit = 5) {
        try {
            $viewedVendorProfiles = $this->dbFetchAll("
                SELECT DISTINCT vpv.vendor_profile_id, vp.business_city,
                                GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR ', ') AS viewed_services
                FROM user_vendor_views vpv
                JOIN vendor_profiles vp ON vpv.vendor_profile_id = vp.id
                LEFT JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                LEFT JOIN vendor_services vs ON vso.service_id = vs.id
                WHERE vpv.user_id = ?
                GROUP BY vpv.vendor_profile_id
                ORDER BY vpv.viewed_at DESC
                LIMIT 5
            ", [$userId]);

            $recentSearchTerms = $this->dbFetchAll("
                SELECT message_content
                FROM ai_chat_messages
                WHERE session_id IN (SELECT id FROM ai_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3)
                AND message_type = 'user'
                ORDER BY created_at DESC
            ", [$userId]);

            $keywords = [];
            $preferredLocations = [];
            $preferredServiceIds = [];

            // Extract keywords from recent searches
            foreach ($recentSearchTerms as $term) {
                $content = strtolower($term['message_content']);
                // Simple keyword extraction (can be improved with NLU)
                if (strpos($content, 'vendor') !== false || strpos($content, 'suggest') !== false || strpos($content, 'find') !== false) {
                    // This message might contain search criteria
                    $words = explode(' ', $content);
                    foreach ($words as $word) {
                        $word = trim($word, ".,!?\"'");
                        if (strlen($word) > 2 && !in_array($word, ['vendor', 'suggest', 'find', 'for', 'a', 'an', 'the', 'in', 'and', 'or', 'my', 'i', 'me', 'want', 'need', 'how', 'much'])) {
                            $keywords[] = $word;
                        }
                    }
                }
            }

            // Extract preferred locations and services from viewed vendors
            foreach ($viewedVendorProfiles as $vendorView) {
                if (!empty($vendorView['business_city'])) {
                    $preferredLocations[] = $vendorView['business_city'];
                }
                if (!empty($vendorView['viewed_services'])) {
                    $services = explode(', ', $vendorView['viewed_services']);
                    foreach ($services as $serviceName) {
                        $serviceId = $this->dbFetch("SELECT id FROM vendor_services WHERE service_name = ?", [$serviceName]);
                        if ($serviceId) {
                            $preferredServiceIds[] = $serviceId['id'];
                        }
                    }
                }
            }
            
            $combinedSearchQuery = implode(' ', array_unique($keywords));
            $combinedLocations = implode(', ', array_unique($preferredLocations));
            $combinedServiceIds = array_unique($preferredServiceIds);

            // Fallback: if no specific activity, get highly-rated vendors
            if (empty($combinedSearchQuery) && empty($combinedLocations) && empty($combinedServiceIds)) {
                return $this->dbFetchAll("
                    SELECT vp.id, vp.business_name, vp.business_city, vp.rating, vp.total_reviews, up.profile_image,
                           GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR ', ') AS offered_services_names
                    FROM vendor_profiles vp
                    JOIN users u ON vp.user_id = u.id
                    LEFT JOIN user_profiles up ON u.id = up.user_id
                    LEFT JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                    LEFT JOIN vendor_services vs ON vso.service_id = vs.id
                    WHERE vp.verified = TRUE
                    GROUP BY vp.id
                    ORDER BY vp.rating DESC, vp.total_reviews DESC
                    LIMIT ?
                ", [$limit]);
            }

            // Build dynamic query for personalized recommendations
            $query = "
                SELECT 
                    vp.id, vp.business_name, vp.website, vp.rating, vp.total_reviews, 
                    vp.business_address, vp.business_city, vp.business_state, vp.business_country,
                    vp.service_radius,
                    up.profile_image, -- Fetch profile image from user_profiles
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR '; ') AS offered_services_names
                FROM vendor_profiles vp
                LEFT JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                LEFT JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                WHERE vp.verified = TRUE
            ";
            
            $params = [];
            $conditions = [];

            if (!empty($combinedSearchQuery)) {
                $conditions[] = "(vp.business_name LIKE ? OR vp.business_address LIKE ? OR vs.service_name LIKE ?)";
                $likeParam = '%' . $combinedSearchQuery . '%';
                $params = array_merge($params, [$likeParam, $likeParam, $likeParam]);
            }

            if (!empty($combinedLocations)) {
                $locationPlaceholders = implode(',', array_fill(0, count(array_unique($preferredLocations)), '?'));
                $conditions[] = "vp.business_city IN ({$locationPlaceholders})";
                $params = array_merge($params, array_unique($preferredLocations));
            }

            if (!empty($combinedServiceIds)) {
                $serviceIdPlaceholders = implode(',', array_fill(0, count($combinedServiceIds), '?'));
                $conditions[] = "vso.service_id IN ({$serviceIdPlaceholders})";
                $params = array_merge($params, $combinedServiceIds);
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            $query .= " GROUP BY vp.id
                         ORDER BY vp.rating DESC, vp.total_reviews DESC
                         LIMIT ?";
            $params[] = $limit;
            
            $vendors = $this->dbFetchAll($query, $params);

            // Refine scoring if needed based on detailed match, but current scoring already uses rating.
            // For now, return direct results, as detailed scoring on "viewed" criteria is complex without more data.
            return $vendors;

        } catch (PDOException $e) {
            error_log("Personalized Vendor Recommendation PDO Error: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log("Personalized Vendor Recommendation General Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generates a natural language summary of recommended vendors using OpenAI.
     * @param array $vendors Array of recommended vendor data.
     * @param array $event_details Details of the event.
     * @return string AI-generated summary or an error message.
     */
    public function generateVendorSummary(array $vendors, array $event_details) {
        if (empty($this->apiKey)) {
            return "OpenAI API key is not configured for vendor summarization.";
        }
        if (empty($vendors)) {
            return "No suitable vendors found for your event at this time. Please adjust your criteria or try again later.";
        }

        $event_summary = "Event Type: " . ($event_details['type_name'] ?? 'N/A') . "\n" .
                         "Date: " . ($event_details['event_date'] ?? 'N/A') . "\n" .
                         "Guests: " . ($event_details['guest_count'] ?? 'N/A') . "\n" .
                         "Budget: $" . number_format($event_details['budget_min'] ?? 0, 2) . " - $" . number_format($event_details['budget_max'] ?? 0, 2) . "\n" .
                         "Location: " . ($event_details['location_string'] ?? 'N/A');

        $vendor_list_for_ai = "Here are the top vendors:\n";
        foreach ($vendors as $index => $vendor) {
            $vendor_list_for_ai .= ($index + 1) . ". " . $vendor['business_name'] . "\n" .
                                   "   - Services: " . ($vendor['offered_services_names'] ?? 'N/A') . "\n" .
                                   "   - Rating: " . number_format($vendor['rating'] ?? 0, 1) . " stars (" . ($vendor['total_reviews'] ?? 0) . " reviews)\n" .
                                   "   - Estimated Price Range: $" . number_format($vendor['avg_min_price'] ?? 0, 2) . " - $" . number_format($vendor['avg_max_price'] ?? 0, 2) . "\n" .
                                   "   - Location: " . ($vendor['business_city'] ?? 'N/A') . ", " . ($vendor['business_country'] ?? 'N/A') . "\n" .
                                   "   - Score: " . number_format($vendor['score'] ?? 0, 2) . "\n";
        }

        $system_prompt = "You are an event planning AI assistant. You have been provided with event details and a list of recommended vendors. Your task is to provide a friendly, concise, and helpful summary of these vendor recommendations to the user. Highlight why each vendor is a good fit based on their services, ratings, and relevance to the event. Encourage the user to check their profiles for more details. Focus on the top 3-5 vendors if many are provided.";
        $user_prompt = "Event Details:\n" . $event_summary . "\n\nRecommended Vendors:\n" . $vendor_list_for_ai . "\n\nPlease summarize these recommendations for the user.";

        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ];

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type' => 'application/json', // Corrected syntax here from ':' to '=>'
                    'Authorization: Bearer ' . $this->apiKey
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-3.5-turbo', // Or a more advanced model if preferred
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ])
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('CURL error: ' . $error);
            }

            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                throw new Exception('OpenAI API error: ' . $data['error']['message']);
            }
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new Exception('OpenAI API response missing content.');
            }

            return trim($data['choices'][0]['message']['content']);

        } catch (Exception $e) {
            error_log("OpenAI Vendor Summary Error: " . $e->getMessage());
            return "I apologize, but I couldn't generate detailed vendor summaries at this moment due to an AI service error. You can still check their profiles manually.";
        }
    }


    private function calculateVendorScore($vendor, $event) {
        $scores = [
            'location' => $this->calculateLocationScore($vendor, $event),
            'availability' => ($vendor['availability_score'] ?? 0) * 0.3,
            'price' => $this->calculatePriceScore($vendor, $event),
            'reviews' => ($vendor['rating'] ?? 0) * 0.2
        ];
        
        return array_sum($scores);
    }

    private function calculateLocationScore($vendor, $event) {
        // If event_lat/lng is not available from event details (e.g., from form input directly)
        // We will fallback to a neutral score or attempt city-based matching (not implemented here fully).
        // For now, if exact lat/lng are missing for the event, score is neutral or relies on implicit city match by query.
        if (!isset($vendor['business_lat']) || !isset($vendor['business_lng']) || !isset($event['event_lat']) || !isset($event['event_lng'])) {
            // If we relied on city/address LIKE in query, assume a basic match for score.
            return 0.7; // Moderate score if no precise geo-match, implies city match or nearby.
        }
        
        $distance = $this->calculateDistance(
            $vendor['business_lat'], $vendor['business_lng'],
            $event['event_lat'], $event['event_lng']
        );
        
        // If service_radius is 0 or null, assume local/perfect fit if distance is very small
        if (($vendor['service_radius'] ?? 0) == 0 && $distance < 5) return 1; // Very local
        if (($vendor['service_radius'] ?? 0) >= $distance) return 1; // Within service radius
        
        return 0.2; // Not within service radius
    }

    private function calculatePriceScore($vendor, $event) {
        $avgPrice = (($vendor['avg_min_price'] ?? 0) + ($vendor['avg_max_price'] ?? 0)) / 2;
        $eventBudget = (($event['budget_min'] ?? 0) + ($event['budget_max'] ?? 0)) / 2;

        if ($eventBudget == 0 || ($event['budget_min'] == 0 && $event['budget_max'] == 0)) return 0.5; // Neutral if event budget is TBD

        // If vendor price is within or below event budget
        if ($avgPrice >= ($event['budget_min'] ?? 0) && $avgPrice <= ($event['budget_max'] ?? PHP_FLOAT_MAX)) return 1; // Within budget
        if ($avgPrice < ($event['budget_min'] ?? 0) && $avgPrice > 0) return 0.8; // Below budget (good)
        if ($avgPrice > ($event['budget_max'] ?? PHP_INT_MIN)) return 0.2; // Above budget (less good)

        return 0.5; // Default if no clear fit
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        // Haversine formula implementation
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    // Helper methods for database interaction
    private function dbFetch($query, $params = []) {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function dbFetchAll($query, $params = []) {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
