/*
 * Copyright (C) 2023  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import java.io.File;
import java.util.Date;
import java.util.Properties;

import javax.activation.DataHandler;
import javax.activation.DataSource;
import javax.activation.FileDataSource;
import javax.mail.*;
import javax.mail.event.*;
import javax.mail.internet.*;

import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;

public class Emailer implements TransportListener, ConnectionListener{
	private Logger	logger;
	private String	outgoingMailServer				= "smtp.host.com";
	private boolean	authenticateOutgoingMail	= false;
	private String	outgoingMailAccount				= "account_name";
	private String	outgoingMailPort					= "25";
	private String	outgoingMailPassword;
	private String	sendEmailsFrom;
	
	public Emailer(Ini configIni, Logger logger){
		this.logger = logger;
		outgoingMailServer = configIni.get("Mail", "host");
		String authenticateMail = configIni.get("Mail", "smtpAuth");
		if (authenticateMail != null){
			authenticateOutgoingMail = Boolean.parseBoolean(authenticateMail);
		}
		outgoingMailAccount = configIni.get("Mail", "account");
		outgoingMailPort = configIni.get("Mail", "port");
		outgoingMailPassword = Util.cleanIniValue(configIni.get("Mail", "password"));
		sendEmailsFrom = Util.cleanIniValue(configIni.get("Mail", "sendEmailsFrom"));
	}

	public boolean sendEmail(String emailToAddress, String emailFrom, String subject, String content, File[] attachments) {
		Transport newTransport = null;
		try {
			Properties sessionProperties = new Properties();
			sessionProperties.put("mail.store.protocol", "POP3");
			sessionProperties.put("mail.transport.protocol", "SMTP");
			sessionProperties.put("mail.host", outgoingMailServer);
			sessionProperties.put("mail.smtp.port", outgoingMailPort);
			sessionProperties.put("mail.user", outgoingMailAccount);

			Authenticator auth = null;
			// zsLogger.write("authenticateOutgoingMail = " +
			// authenticateOutgoingMail);
			if (authenticateOutgoingMail) {
				auth = new SimpleAuthenticator();
				sessionProperties.put("mail.smtp.auth", "true");
			}
			Session newSession = Session.getDefaultInstance(sessionProperties, auth);
	
			Message newMessage = new MimeMessage(newSession);
			newMessage.setFrom(new InternetAddress(sendEmailsFrom));
			newMessage.setReplyTo(new Address[]{new InternetAddress(emailFrom)});
			String[] receipientsNames = emailToAddress.split(";");
			Address[] receipients = new Address[receipientsNames.length];
			for (int i = 0; i < receipientsNames.length; i++) {
				receipients[i] = new InternetAddress(receipientsNames[i]);
			}
			newMessage.setRecipients(Message.RecipientType.TO, receipients);
			newMessage.setSubject(subject);
			newMessage.setSentDate(new Date());

			if (attachments == null || attachments.length == 0) {
				newMessage.setText(content);
			} else {
				// Create the message part
				BodyPart messageBodyPart = new MimeBodyPart();
				// Fill the message
				messageBodyPart.setText(content);

				Multipart multipart = new MimeMultipart();
				multipart.addBodyPart(messageBodyPart);

				for (int i = 0; i < attachments.length; i++) {
					// Part two is attachment
					if (attachments[i] != null && attachments[i].length() != 0) {
						BodyPart fileBodyPart = new MimeBodyPart();
						DataSource source = new FileDataSource(attachments[i].getPath());
						fileBodyPart.setDataHandler(new DataHandler(source));
						fileBodyPart.setFileName(attachments[i].getName());
						multipart.addBodyPart(fileBodyPart);
					}
				}

				// Put parts in message
				newMessage.setContent(multipart);
			}
			logger.debug("Built Message ");

			newTransport = newSession.getTransport(receipients[0]);
			logger.debug("Got transport");

			newTransport.addConnectionListener(this);
			newTransport.addTransportListener(this);

			if (authenticateOutgoingMail) {
				logger.debug("Connecting with authentication");
				newTransport.connect(outgoingMailServer, outgoingMailAccount, outgoingMailPassword);
			} else {
				logger.debug("Connecting without authentication");
				newTransport.connect();
			}
			logger.debug("Connected to transport");

			newMessage.saveChanges();
			newTransport.sendMessage(newMessage, receipients);
			logger.debug("Finished sending email");
			return true;
		} catch (Error e) {
			logger.error("Error sending e-mail ", e);
			return false;
		} catch (Exception e) {
			logger.error("Exception sending e-mail ", e);
			return false;
		} finally {
			if (newTransport != null) {
				try {
					newTransport.close();
				} catch (Exception e) {
					logger.error("Exception sending e-mail ", e);
				}
			}
		}
	}

	public void messageDelivered(TransportEvent e) {
		logger.debug("The message was delivered.");
	}

	public void messageNotDelivered(TransportEvent e) {
		logger.debug("The message was not delivered.");
	}

	public void messagePartiallyDelivered(TransportEvent e) {
		logger.debug("The message was partially delivered.");
	}

	public void opened(ConnectionEvent e) {
		logger.debug("The transport was opened.");
	}

	public void disconnected(ConnectionEvent e) {
		logger.debug("The transport was disconnected.");
	}

	public void closed(ConnectionEvent e) {
		logger.debug("The transport was closed.");
	}

	private class SimpleAuthenticator extends Authenticator {
		public SimpleAuthenticator() {
			super();
			logger.debug("Created Simple Authenticator");
		}

		protected PasswordAuthentication getPasswordAuthentication() {
			logger.debug("Returning Password Authentication(" + outgoingMailAccount + "," + outgoingMailPassword + ")");
			return new PasswordAuthentication(outgoingMailAccount, outgoingMailPassword);
		}
	}
}
