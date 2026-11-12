FROM node:22-alpine
WORKDIR /app
COPY --chown=node:node editor.html ./editor.html
COPY --chown=node:node LICENSE ./LICENSE
COPY --chown=node:node 网站/package.json 网站/core.cjs 网站/server.cjs ./网站/
COPY --chown=node:node 网站/public ./网站/public
USER node
WORKDIR /app/网站
ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000
CMD ["node", "server.cjs"]
