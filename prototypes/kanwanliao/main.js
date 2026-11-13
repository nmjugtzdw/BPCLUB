'use strict'

const search = document.querySelector('#search')
const filters = Array.from(document.querySelectorAll('[data-filter]'))
const threads = Array.from(document.querySelectorAll('.thread'))
const empty = document.querySelector('#empty')
const result = document.querySelector('#result')
let category = '全部'

function render() {
  const query = search.value.trim().toLocaleLowerCase()
  let visible = 0
  for (const thread of threads) {
    const matches = (category === '全部' || thread.dataset.category === category) &&
      (thread.dataset.search || '').toLocaleLowerCase().includes(query)
    thread.hidden = !matches
    if (matches) visible += 1
  }
  empty.hidden = visible !== 0
  result.textContent = `共 ${visible} 条示例话题`
}

for (const button of filters) {
  button.addEventListener('click', () => {
    category = button.dataset.filter
    for (const item of filters) {
      const selected = item === button
      item.classList.toggle('selected', selected)
      item.setAttribute('aria-pressed', String(selected))
    }
    render()
  })
}
search.addEventListener('input', render)
