// Stephan Ohlsson
// stephan.ohlsson@gmail.com
// Simple HTTP server 
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>
#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <stdio.h>
#include <unistd.h> 
#include <string.h>
#include <stdlib.h>
#include <arpa/inet.h>
#define BUFSIZE 1024
void error(char *msg)
{
	perror(msg);
	exit(0);
}
void child(int newsock, int n, char buffer[BUFSIZE], char file[BUFSIZE]) {
	n = recv(newsock,buffer,BUFSIZE-1,0);
	if (n < 1)
	error("Reading from socket");
	else {
		buffer[n]='\0';
		printf("The message from %s is %s\n",inet_ntoa((struct in_addr)from.sin_addr),buffer);
	}
	// Getting file information
	m = 0;
	while(buffer[m] != '/')
	m++;
	for(p = 0; buffer[m] != ' '; m++, p++) {
		file[p] = buffer[m];
	}
	file[p] = '\0';
	printf("RETRIEVING INFORMATION FOR FILE %s\n", file);
	close(fd2);
	// If they sent '/'...
	if(p == 1) {
		getdir(buffer, newsock);
	}
	// They asked for a file
	else {
		getfile(file, newsock, buffer);
	}
	close(newsock);
	exit(0);
}
void getdir(char buffer[BUFSIZE], int newsock) {
	int pid2, m, n, retval, fd, contentLength;
	fd = open("temp", O_RDWR|O_CREAT);
	if(fd < 0) 
	perror("Error opening temp file\n");
	pid2 = fork();
	if(pid2 == 0) {
		retval = dup2(fd,1);
		if(retval != 1)
		perror("Error on dup2 ");
		if(execv("/bin/ls", parmList) == -1) {
			perror("Error on execv\n");
			exit(0);
		}
	}
	wait();
	// Get content length
	contentLength = 0;
	fd = open("temp", O_RDWR);
	while(1) {
		m = read(fd, buffer, 64);
		contentLength += m;
		if(m < 64)
		break;
	}
	sprintf(buffer, "HTTP/1.1 200 OK\nContent-Length: %d\nContent-Type: text/plain\n\n", contentLength);
	n = send(newsock, buffer, strlen(buffer), 0);
	if(n < strlen(buffer))
	perror("Writing");
	fd = open("temp", O_RDWR);
	if(fd < 0)
	perror("Error opening temp file");
	while(1) {
		m = read(fd, buffer, 64);
		n = send(newsock, buffer, m, 0);
		if(n < m) 
		perror("Writing");
		if(m < 64)
		break;

	}
	if(unlink("temp") == -1)
	perror("Error deleting temp");
}
void getfile(char file[BUFSIZE] int newsock, char buffer[BUFSIZE]) {
	int m, n, fd, contentLength;
	m = n = 0;
	if(file[0] == '/')
	m++;
	while(file[m] != '\0') {
		file[n] = file[m];
		n++;
		m++;
	}
	file[n] = '\0';
	fd = open(file, O_RDONLY);
	// Error 404
	if(fd < 0) {
		strcpy(buffer,"HTTP/1.1 404 NOT FOUND");
		n = send(newsock, buffer,13, 0);
		if(n < 9)
		perror("Writing");
		exit(0);
	}
	// Returning a file
	else {
		contentLength = 0;
		// Getting and printing content length
		while(1) {
			m = read(fd, buffer, 64);
			contentLength += m;
			if(m < 64)
			break;
		}
		sprintf(buffer, "HTTP/1.1 200 OK\nContent-Length: %d\nContent-Type: text/html\n\n", contentLength);
		n = send(newsock, buffer, strlen(buffer), 0);
		if(n < strlen(buffer))
		perror("Writing");
		fd = open(file, O_RDONLY);
		while(1) {
			m = read(fd, buffer, 64);
			n = send(newsock, buffer, m, 0);
			if(n < m)
			perror("writing");
			if(m < 64)
			break;
		}
	}
}
int main(int argc, char *argv[])
{
	int sock, newsock, len, fromlen,  n, pid, m, p, fd, fd2, retval, pid2, contentLength;
	char file[BUFSIZE];
	char *const parmList[] = {"/bin/ls", NULL};
	unsigned short port;
	struct sockaddr_in server;
	struct sockaddr_in from;
	char buffer[BUFSIZE];

	if (argc < 2) {
		fprintf(stderr,"usage %s portnumber\n",argv[0]);
		exit(0);
	}
	port = (unsigned short) atoi(argv[1]);
	sock=socket(AF_INET, SOCK_STREAM, 0);
	if (sock < 0) error("Opening socket");
	server.sin_family=AF_INET;
	server.sin_addr.s_addr=INADDR_ANY;
	server.sin_port=htons(port);  
	len=sizeof(server);
	if (bind(sock, (struct sockaddr *)&server, len) < 0) 
	error("binding socket");
	fromlen=sizeof(from);
	listen(sock,5);
	fd2 = open("weblog", O_RDWR|O_CREAT, S_IRWXU);
	if(fd2 < 0)
	perror("Opening weblog");
	dup2(fd2, 1);
	if(dup2 < 0)
	perror("Error on dup2");
	while (1) {
		newsock=accept(sock, (struct sockaddr *)&from, &fromlen);
		pid = fork();
		if (pid == 0) { 
			child(newsock,n,buffer,file);
		}
		close (newsock);
	}
	return 0; /* we never get here */
}

