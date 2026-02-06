package net.wyrihaximus.redirect;

import reactor.core.publisher.Flux;
import reactor.core.publisher.Mono;

import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.server.ServerRequest;
import org.springframework.web.reactive.function.server.ServerResponse;
import reactor.core.publisher.Flux;
import reactor.core.publisher.Mono;
import org.springframework.web.util.UriComponentsBuilder;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.web.reactive.function.server.RequestPredicates.*;
import static org.springframework.web.reactive.function.server.RouterFunctions.route;
// PersonHandler handler = new PersonHandler(repository);
//
// RouterFunction<ServerResponse> route = route() (1)
// 	.GET("/person/{id}", accept(APPLICATION_JSON), handler::getPerson)
// 	.GET("/person", accept(APPLICATION_JSON), handler::listPeople)
// 	.POST("/person", handler::createPerson)
// 	.build();
import java.net.URI;

public class RedirectHandler {
//
// 	private final UserRepository userRepository;
//
// 	private final CustomerRepository customerRepository;
//
// 	public MyRestController(UserRepository userRepository, CustomerRepository customerRepository) {
// 		this.userRepository = userRepository;
// 		this.customerRepository = customerRepository;
// 	}

	Mono<ServerResponse> redirect(ServerRequest request) {
        return ServerResponse.permanentRedirect(
            URI.create(
                request.uriBuilder().host("example.com").toUriString()
            )
        ).build();
    }
}